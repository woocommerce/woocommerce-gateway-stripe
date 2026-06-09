# Exploration — Site identification in Agentic Commerce `external_id` (STRIPE-968)

> **Status: EXPLORATION ONLY.** This document and the sibling spike file are notes and
> throwaway prototype code. Nothing here is wired into the plugin runtime and nothing here
> should be merged as-is. It exists to compare approaches and surface open questions before
> any implementation is scoped.

## Problem

When two or more WooCommerce sites are connected to the **same** Stripe account, an agentic
delegated-checkout event for that account cannot be attributed to a specific site by anything
the plugin currently sends. A delegated checkout is built from a product catalog feed whose
catalog row id is the bare **SKU** (`class-wc-stripe-agentic-commerce-product-mapper.php` →
`get_id()`), and order resolution at webhook time looks that SKU up locally via
`wc_get_product_id_by_sku()` (`class-wc-stripe-agentic-commerce-product-resolver.php:61`). If
the same SKU exists on both sites, **both** sites can resolve a product and create an order
for the same checkout → duplicate / wrong-site orders.

### Why the STRIPE-1194 webhook account guard does not cover this

The recently added guard (`WC_Stripe_Webhook_Handler::event_belongs_to_connected_account()`)
compares the event's account (`event.account` / `event.context`) against this store's
**connected account ID**. It isolates *cross-account* events. It cannot disambiguate sites
that **share** an account, because `event.context === connected_account` is true on every such
site, so all of them pass the guard.

| Axis | STRIPE-1194 guard | STRIPE-968 (this) |
| --- | --- | --- |
| Granularity | Account | **Site** |
| Answers | "From my connected account?" | "Which of N sites on one account owns this?" |

The guard **narrows** the blast radius (cross-account now rejected) but leaves the
same-account-multi-site case open. The two are complementary, not redundant.

## What identifiers exist today

| Identifier | Where | Site-specific? | Echoed back on the checkout event? |
| --- | --- | --- | --- |
| Catalog row `id` (→ `price.external_reference`) | feed `id` column = SKU/product-ID | ❌ (bare SKU) | ✅ (this is what resolution uses) |
| `link` (product permalink) | feed `link` column | ✅ (`home_url`-based) | ❓ not surfaced as `external_reference` today |
| Stripe account ID | connection settings | ❌ (shared in the failure case) | ✅ (but identical across sites) |
| Per-site agentic webhook secret | `wc_stripe_agentic_commerce_webhook_secret` option | ✅ (pasted per site) | n/a (auth only) |

Key constraint: **the only field reliably echoed back at webhook resolution time is
`external_reference` (the SKU).** Any site signal that must survive the round-trip has to ride
on that value unless Stripe also echoes another field (open question below).

## Candidate approaches

### A. Namespace `external_reference` with a stable per-site token  ⭐ most self-contained

Build catalog ids as `"{site_token}:{sku}"`; at resolution, split on the delimiter, **reject
when the token isn't this site's**, then resolve the remaining SKU. `site_token` is a short,
stable, opaque value derived once per site (see "Site token" below).

- **Pros:** deterministic; rides on the one field guaranteed to round-trip; no dependency on
  Stripe metadata features; gives an explicit, testable "not my site → skip" at resolution.
- **Cons:** changes the `external_reference` format → back-compat path needed for catalogs
  already keyed by bare SKU; the namespaced id shows in Stripe's merchant UI; the resolver and
  product mapper both change; must keep the delimiter out of valid SKUs.
- **Touch points:** `product-mapper::get_id()`, `product-resolver::resolve_product_id_by_external_reference()`,
  feed schema doc, both webhook resolution paths (customize + finalize + order mapper).

### B. Carry the site in feed/line-item metadata, echoed back  (ideal *if* Stripe supports it)

Attach a site token (or `home_url`) as catalog/line-item metadata at feed upload, and have
Stripe surface it back on the delegated-checkout event. Resolution compares it and skips on
mismatch — without overloading the human-facing SKU.

- **Pros:** keeps `external_reference` clean; explicit, purpose-built field.
- **Cons:** **entirely gated on a Stripe API capability we have not confirmed** (does the Files
  API / delegated checkout echo a per-row metadata field back on the event?). If unsupported,
  this is a non-starter. This is the crux of the PR #5032 discussion.

### C. Per-site webhook-endpoint binding  (verify-first — may already mitigate)

If a delegated checkout is bound to the **originating** webhook endpoint/feed and Stripe only
delivers its events to that endpoint, site disambiguation is automatic and no `external_id`
change is needed — a defensive guard would suffice. If instead events are **broadcast** to all
endpoints registered on the shared account, A or B is required.

- **Action:** confirm Stripe's delivery semantics for delegated checkout under multiple
  endpoints on one account. This single answer decides whether STRIPE-968 needs A/B at all or
  just a lightweight defensive check.

### D. Defensive site check at order creation (necessary backstop, insufficient alone)

Regardless of A–C, add an explicit "does this checkout belong to this site?" gate before
`create_order_from_checkout_session()` so a mismatch is a logged no-op rather than a silent
duplicate order. Alone it's insufficient (bare SKUs resolve on both sites), but it's the right
place to *enforce* whatever signal A/B/C provides.

### E. One Stripe account per site  (policy / out of scope)

The only airtight guarantee, but it contradicts the premise (shared accounts) and is an
operational/docs decision, not a code change. Noted for completeness.

## Site token — how to derive one

Requirements: stable across requests, unique per site, opaque-ish, short enough for the SKU
namespace, and regenerable if a site is cloned (staging vs. prod must differ).

Options considered:
- `home_url()` host — readable but long and leaks the domain into Stripe UI; changes on
  domain migration.
- `wp_hash( home_url() )` truncated — short, stable, opaque; still tied to URL.
- A generated-and-stored random token (option), set once at agentic setup — fully decoupled
  from URL (survives domain change), but must be persisted and included in the feed build.

Leaning toward a **stored random token** generated at agentic onboarding, with `home_url` host
as a human-readable fallback only. See the spike for a sketch.

## Open questions (must answer before scoping implementation)

1. **(Blocks everything)** Does Stripe deliver a delegated checkout's events only to the
   originating endpoint, or broadcast to all endpoints on the shared account? (→ decides C vs A/B)
2. Can catalog/line-item **metadata** be echoed back on the delegated-checkout event? (→ enables B)
3. Back-compat: are there live catalogs keyed by bare SKU that a format change to
   `external_reference` would break mid-flight? What's the migration/transition window?
4. Where should the comparison live and what's the failure contract — silent skip, logged
   skip (preferred, mirrors STRIPE-1194), or surfaced for diagnostics?
5. Staging/clone safety: how do we guarantee a cloned site gets a *different* token so it can't
   claim prod's checkouts?

## Recommendation (for discussion, not implementation)

1. **Answer Q1/Q2 with Stripe first.** They collapse the option space.
2. If events are broadcast and metadata is **not** echoed → pursue **A (namespaced
   `external_reference`)** + **D (order-creation gate)**, with a back-compat path that accepts
   both `"{token}:{sku}"` and legacy bare `"{sku}"` during a transition.
3. If metadata **is** echoed → prefer **B** + **D** (keeps SKUs clean).
4. If delivery is endpoint-bound (C) → a thin **D** defensive check is likely enough.

## Suggested next steps

- [ ] Confirm Stripe delivery + metadata semantics (Q1/Q2) — likely needs PAYOPS-1766 / Stripe contacts.
- [ ] Decide on the site-token source (Q5).
- [ ] Scope the chosen approach as a separate implementation issue with back-compat + tests
      (match/mismatch across both sites, legacy bare-SKU acceptance, staging-clone safety).

## Grounding references

- `includes/agentic-commerce/class-wc-stripe-agentic-commerce-product-mapper.php` — `get_id()` (catalog id = SKU)
- `includes/agentic-commerce/class-wc-stripe-agentic-commerce-product-resolver.php` — `resolve_product_id_by_external_reference()`
- `includes/agentic-commerce/class-wc-stripe-agentic-commerce-order-mapper.php` — `create_order_from_checkout_session()`, `map_line_items()`
- `includes/agentic-commerce/class-wc-stripe-agentic-commerce-files-api-delivery.php` — feed upload/import
- `includes/class-wc-stripe-webhook-handler.php` — `event_belongs_to_connected_account()` (STRIPE-1194 account guard)
