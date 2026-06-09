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

## Research findings (deep-research over Stripe + ACP public docs, 2026-06)

A cited deep-research pass answered most of the gating questions from public documentation.
High-confidence conclusions (3-0 adversarial verification unless noted):

- **Q1 — delivery is account+type scoped, not feed/session bound.** Stripe webhook delivery is
  filtered only by account dimension and per-endpoint `enabled_events` (event *type*); there is
  no documented field that routes a delegated checkout's events to the endpoint/feed that
  produced it. Stripe staff on the official `api-discuss` list state, for the exact two-sites /
  one-account case: *"All webhook endpoints will get all those events and it isn't 'per
  transaction'"* — recommending filter-on-receive.
  ([webhooks](https://docs.stripe.com/webhooks),
  [webhook_endpoints API](https://docs.stripe.com/api/webhook_endpoints),
  [api-discuss thread](https://groups.google.com/a/lists.stripe.com/g/api-discuss/c/8EhZtyXbQu8))
- **Q1 nuance — sync hooks vs. async events split the risk.** The `customize_checkout` /
  `finalize_checkout` hooks are **synchronous request/response** hooks configured as a *single*
  endpoint on the Dashboard Agentic Commerce settings page — effectively one agentic endpoint
  **per account**, so two sites on one account can't both own it. The order-creating path runs
  off the **async, broadcast** event system, which fans out to *all* endpoints on the account.
  **So the duplicate/wrong-site-order risk concentrates on the async order path, not the sync
  hooks.** (Public docs are silent on whether a second standard endpoint can also subscribe to
  `v1.delegated_checkout.*` in parallel — confirm with Stripe.)
  ([for-sellers](https://docs.stripe.com/agentic-commerce/for-sellers),
  [enable-in-context-selling](https://docs.stripe.com/agentic-commerce/enable-in-context-selling-on-ai-agents))
- **Q2 — no per-product metadata round-trip (Approach B is a dead end per docs).** The feed
  exposes only structured fields (`id`/SKU → `price.external_reference`, price, tax codes,
  GTIN/MPN) plus `custom_label_0…4` (explicitly *"for internal purposes only"*, 100 chars).
  Nothing — not even custom labels — is **documented as echoing back** onto delegated-checkout
  events; `line_item_details` echoes only `id, sku_id, name, quantity, unit_amount, amounts,
  tax_rates`. The ACP feed schema has `metadata` only at the *feed-resource* level, not
  per-product. → A free-form site identifier cannot ride through the documented round-trip.
  ([product-catalog](https://docs.stripe.com/agentic-commerce/product-catalog),
  [ACP schema](https://github.com/agentic-commerce-protocol/agentic-commerce-protocol))
- **Q3 — `external_reference` is "alphanumeric", ≤100 chars, no namespacing guidance.** The
  catalog `id` is the unique product identifier (recommended SKU, kept stable, typed *"String
  (alphanumeric) Maximum 100 characters"*, example `SKU12AB3456`); it surfaces back as
  `price.external_reference`. No prefixing/namespacing is documented anywhere, and the canonical
  Price object reference doesn't even list `external_reference`. **The "alphanumeric" label is
  the key risk for Approach A**: a delimiter like `~` or `_` (and the `~` used in the spike) may
  be rejected. An alphanumeric-only scheme (e.g. fixed-width token prefix, no separator) plus
  Stripe confirmation would be required.
  ([product-catalog](https://docs.stripe.com/agentic-commerce/product-catalog),
  [Price object](https://docs.stripe.com/api/prices/object))

**Net effect on the options:** B is effectively ruled out by public docs. C is *partially* true
for the sync hooks (single per-account endpoint) but **false** for the async order events that
actually create orders. So the live risk = async order events broadcasting to all endpoints on
a shared account → **A + D remain the viable path**, with A constrained to an alphanumeric-safe
encoding.

## Open questions (remaining — need Stripe / PAYOPS-1766, not public docs)

1. ~~Are delegated-checkout events bound to the originating endpoint or broadcast?~~ **Answered:**
   account+type scoped, broadcast to all matching endpoints; no feed/session binding.
2. ~~Can per-product metadata echo back on the event?~~ **Answered (per public docs): no.** Sub-question
   left for Stripe: are `custom_label_0…4` *ever* surfaced back on delegated-checkout events?
   (Docs are silent, not an explicit "no".)
3. **(Now the key blocker)** Does Stripe permit a non-alphanumeric delimiter in the catalog
   `id` / `external_reference` (e.g. `~`, `_`, `:`)? If not, confirm an alphanumeric-only
   namespacing scheme is acceptable and round-trips intact.
4. For the async order path: confirm whether both sites' standard endpoints on a shared account
   actually receive each other's order events in practice (the general fan-out rule says yes).
5. Back-compat: live catalogs keyed by bare SKU — transition window for an `external_reference`
   format change.
6. Failure contract: silent skip vs. logged skip (preferred, mirrors STRIPE-1194) vs. surfaced.
7. Staging/clone safety: guarantee a cloned site gets a *different* token.

## Recommendation (for discussion, not implementation)

1. **Approach A (namespaced `external_reference`) + D (order-creation gate)** is the path public
   docs support. B is ruled out (no metadata round-trip); C only covers the sync hooks, not the
   order-creating async events.
2. Constrain A to an **alphanumeric-only** encoding (no `~`/`_`/`:` delimiter — the spike's `~`
   is at risk) pending Stripe confirmation (Open Q3). E.g. a fixed-length alphanumeric site
   token prefix of known width so the SKU can be recovered by offset, not by separator.
3. Pair with **D**: an explicit, logged "not my site → no-op" gate before
   `create_order_from_checkout_session()`, with a back-compat path accepting legacy bare SKUs
   during transition.
4. Confirm Open Q3/Q4 with Stripe (PAYOPS-1766) before scoping implementation.

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
