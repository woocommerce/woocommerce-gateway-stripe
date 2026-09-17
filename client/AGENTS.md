# client/AGENTS.md

Scope: applies to frontend code under `client/`.

For repository-wide rules, always read the root `AGENTS.md` first.

## CRITICAL Rules

- **CRITICAL:** Keep checkout behavior consistent across shortcode and Blocks flows unless the change is explicitly flow-specific.
- **CRITICAL:** If you change user-facing behavior, add or update tests (Jest and/or E2E as appropriate).
- **CRITICAL:** Keep payment method availability, labels, and icons aligned across UI surfaces.
- **CRITICAL:** Payment method availability rules must come from a single source of truth shared across PHP config and frontend rendering.
- **CRITICAL:** Use shared amount/minor-unit normalization utilities; do not compute Stripe-facing or user-facing amounts ad hoc.
- **CRITICAL:** Prefer incremental updates in existing modules over broad rewrites.

## Structure and Ownership

- Blocks integration: `client/blocks/`
- Settings/admin UI: `client/settings/`, `client/entrypoints/`
- Express checkout: `client/express-checkout/`
- Shared data/state: `client/data/`
- Shared utility logic: `client/utils/`, `client/stripe-utils/`
- Payment method visuals: `client/payment-method-icons/`
- Shared SCSS tokens and mixins: `client/styles/abstracts/` (import the `styles.scss` barrel)
- UPE appearance/theming: `client/styles/upe/` (samples page styles for Stripe PE appearance matching)

## Task-to-Command Matrix

| Task | Command |
| --- | --- |
| Build frontend assets | `npm run build:webpack` |
| Run dev watcher | `npm start` |
| Run JS tests | `npm run test:js` |
| Run JS lint | `npm run lint:js` |
| Auto-fix JS lint | `npm run lint:js-fix` |
| Run CSS/SCSS lint | `npm run lint:css` |

## Frontend Conventions

- Reuse existing shared utilities before adding new helpers.
- Keep feature flags and payment-method gating logic centralized and explicit.
- Preserve existing naming patterns and folder placement used by nearby files.
- Keep dependencies minimal; avoid adding libraries for simple logic.

## Styling Conventions

Style with SCSS stylesheets and stable class names. `@emotion/*` is being removed from `client/`.

- **CRITICAL:** Do not add `@emotion/*` imports. ESLint (`no-restricted-imports`) blocks them everywhere except the files listed in `.eslint-emotion-allowlist.js`. When a file stops importing emotion, remove it from that list; never add to it.
- **Files:** put a `style.scss` next to the component's `index.js` and side-effect import it (`import './style.scss';`). For a single-file component use `<basename>.scss` (for example `settings/card-body.scss`). Do not rename existing `styles.scss` files. There is no Sass prelude, so start each stylesheet with a relative `@use '<path>/styles/abstracts/styles';` when it needs tokens.
- **Class names:** BEM with the `wc-stripe-` prefix — `wc-stripe-<block>__<element>--<modifier>`, one block per component directory. Use `--modifier` for static variants driven by props (size, tone, layout). Reserve `is-*`/`has-*` for transient runtime state.
- **Components:** accept a `className` prop, merge it with `clsx( 'wc-stripe-x', { 'wc-stripe-x--mod': cond }, className )`, and spread remaining props onto the root element. Express prop-driven style differences as modifier classes, not inline styles.
- **Tokens:** take colors from `styles.$gray-*` / `styles.$wp-*`, spacing from `styles.$grid-unit-*`, and the 600px breakpoint from `styles.break-small`. Project tokens live in `client/styles/abstracts/_variables.scss`; add one only when a value has no upstream equivalent and repeats. When converting existing emotion CSS, the rendered output MUST NOT change: substitute a token only where it equals the literal exactly, and leave other values literal with a `// TODO(tokens)` comment.
- **Breakpoints:** `breakpoint( '>660px' )` emits `min-width: 661px`. To reproduce an existing `min-width: 660px` rule, write `@media ( min-width: styles.$wc-stripe-break-row )`.
- **Overriding `@wordpress/components` internals (`.components-*`):** only inside the wrapper component that owns the WP component, and always nested under our block class so the selector has at least two classes. Emotion injected its rules at runtime and won ties by source order; a stylesheet does not, so never rely on load order. Use `!important` only where the emotion CSS being converted already did.
- **Do not reach into another component's internals** from a feature file (for example styling `.wcstripe-inline-notice` from a modal). Add a prop or modifier to that component instead.
- **Legacy class names** (`wcstripe-chip`, `wcstripe-inline-notice`, `wcstripe-confirmation-modal*`, `wcstripe-tooltip*`) may be targeted by merchants' custom admin CSS. Do not rename them in place: when rewriting such a file, emit the legacy class alongside the new `wc-stripe-*` class and style against the new one.
- **RTL:** only the Blocks checkout stylesheet is registered with an RTL replacement; admin stylesheets load the LTR file in every locale.
- **Verification:** there are no visual regression tests. Any PR that moves or changes styles MUST include before/after screenshots of the affected screens at desktop width and below 660px.

## Common Pitfalls

- Updating a payment method in one place only (for example settings but not blocks/icon map).
- Introducing checkout-flow divergence accidentally between classic and blocks.
- Changing state shape in `client/data/` without updating all consumers.
- Forgetting to rebuild assets when source changes require refreshed build output.
- Mixing display amounts and API minor-unit amounts in per-feature logic.
- Safari inline element baseline gap: Stripe Express Checkout Element iframes are inline-level replaced elements. Safari adds extra whitespace below the text baseline (the "image gap"), inflating container height beyond the visible button. Fix with `font-size: 0; line-height: 0` on the container, not by targeting margins.
- Using block layout (`padding-bottom` on children) for vertical button spacing instead of flexbox/grid with explicit `gap`. Block layout margin/padding behavior varies across browsers; flex/grid `gap` is deterministic.
- Using `document.fonts.status` as a gate before subscribing to `document.fonts.ready`. The `ready` promise retains its resolved state — `.then()` runs as a microtask even when fonts are already loaded. A `status` check creates a race window. Always subscribe unconditionally.

## Test Mapping

- JS unit tests and config: `tests/js/`
- End-to-end checkout verification: `tests/e2e/`

For behavior changes affecting checkout, pair unit-level checks with at least one relevant E2E path.
