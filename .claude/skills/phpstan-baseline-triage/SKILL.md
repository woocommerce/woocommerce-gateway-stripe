---
name: phpstan-baseline-triage
description: Use when PHPStan reports new errors and you're considering whether to fix them or baseline them. Triggers include "PHPStan errors", "phpstan baseline", "level 8 errors", "fix phpstan", "phpstan-baseline.neon". Project policy is fix-first, baseline-last; this skill enforces that triage order.
---

# PHPStan Baseline Triage

The repo's CRITICAL rule (from `AGENTS.md`): treat baseline updates as the *last* option, not the first. If we commit baseline-only changes for issues that have a real fix, future contributors inherit a less-typed codebase. The baseline exists for genuine PHPStan limitations, not as a blanket suppressor.

When suppression is unavoidable, prefer an **inline `@phpstan-ignore-next-line` (or `@phpstan-ignore-line`) with a reason comment** over a baseline entry. Inline ignores live next to the code they justify, get reviewed in the same diff, and are removed as soon as the surrounding code changes — baseline entries are silent and easy to forget.

## Procedure

1. **Run the analyzer:**

   ```bash
   npm run phpstan
   ```

   The build fails if any error isn't already in `phpstan-baseline.neon`.

2. **For each new error, classify by category:**

   | Category | Action |
   |----------|--------|
   | Real `null` / `false` handling missing | **Fix the code** |
   | Wrong type annotation that lies about the actual return | **Fix the annotation** |
   | Untyped WP/WC hook return (`mixed`, etc.) | **Add a defensive check at the boundary** |
   | Stripe SDK type that genuinely allows multiple shapes | **Narrow with explicit assertion or fix the call site** |
   | Genuine PHPStan limitation (intersection types, generic variance) | **Add an inline `@phpstan-ignore` comment with a specific error type to ignore as well as descriptive text** |

3. **Fix legitimate issues first.** Most level 8 errors indicate real null/false paths that production hasn't hit yet — the type checker is doing its job.

4. **For genuine PHPStan limitations, prefer an inline ignore over the baseline:**

   ```php
   // @phpstan-ignore function.alreadyNarrowedType (This is the output of a filter that may return other data types.)
   $foo->bar();
   ```

   Reach for `npm run phpstan:baseline` only when an inline ignore is impossible (e.g., the error is reported on generated code or a file you cannot annotate). Inspect the resulting `phpstan-baseline.neon` diff; if the diff added entries you didn't intend to baseline, fix or inline-ignore those instead and re-run.

5. **Don't mix baseline churn with feature work** in the same commit (CRITICAL rule from `AGENTS.md`). Either:

   - Commit the feature without any baseline drift (preferred), or
   - Split into a baseline-only commit and a feature commit.

## What "untyped boundary" means in this codebase

WordPress and WooCommerce APIs return `mixed` constantly. PHPStan flags this aggressively. The right pattern at the boundary is an explicit guard, not a baseline:

```php
$order = wc_get_order( $order_id );
if ( ! $order instanceof WC_Order ) {
    return;
}
// $order is now narrowed for the rest of the function
```

A bare `if ( ! $order )` is not enough — `wc_get_order()` can return a `WC_Order_Refund` or `false`. Use `instanceof` checks where shape matters. The same applies to `wp_get_current_user()`, `WC()->cart`, subscription/pre-order objects, and Stripe SDK responses.

## What NOT to baseline

- Errors in code you just touched (fix first).
- Errors whose message mentions `null` or `false` — those are usually real.
- Errors in critical paths: payment processing, webhook handling, intent controller, customer linking. A type error here is the kind of thing that becomes a fatal in production.

## When baseline is the right answer

- Genuine PHPStan limitations (intersection types it can't follow).
- WP/WC core types that are wrong upstream and will be fixed there.
- Stripe SDK return types that PHPStan over-narrowed.

Prefer the inline `@phpstan-ignore` comment form whenever the file is editable — the reason lives next to the code instead of in a sibling file.

## Verifying after the change

After fixes and any baselining, re-run:

```bash
npm run phpstan
```

Confirm no remaining errors. Then run the smallest relevant test suite for the touched code so a real-fix-versus-paper-fix gets caught:

```bash
npm run test:php -- --filter <ClassNameTest>
```
