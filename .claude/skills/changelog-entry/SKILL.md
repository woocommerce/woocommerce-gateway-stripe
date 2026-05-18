---
name: changelog-entry
description: Use when adding or editing changelog entries for this plugin. Triggers include "add changelog", "changelog entry", "release notes", "update changelog.txt", "update readme.txt", or any PR that introduces user-visible behavior changes. Critical: changelog.txt and readme.txt use different version-header formats and must both be updated.
---

# Changelog Entry — WC Stripe Gateway

Adds entries to `changelog.txt` and `readme.txt` simultaneously, with the right entry type and the right version-header format for each file.

## Critical: two files, two formats

The repo intentionally uses different version headers in each file because the upstream parsers are different:

**`changelog.txt`** (WooCommerce.com parser):

```
xxxx-xx-xx - version 10.7.0
* Type - Description
```

**`readme.txt`** (WordPress.org parser):

```
= 10.7.0 - xxxx-xx-xx =
* Type - Description
```

The `* Type - Description` body is identical, but the version *header* is reversed and uses different delimiters. `bin/changelog.js` is aware of both — converting one to the other breaks release tooling.

## Entry types

| Type | When to use |
|------|-------------|
| **Add** | New feature or capability merchants can use |
| **Update** | Behavior change visible to merchants — including removing public-surface methods that extension authors may have relied on |
| **Fix** | Bug fix |
| **Tweak** | Minor visual/copy change that doesn't fit Add/Update/Fix |
| **Dev** | Internal-only change with no merchant or extension-author impact (CI, test refactors, dev dependencies) |

**The Add/Update vs Dev judgment:** if the change can break someone else's site or workflow when they update, it's `Update`, not `Dev`. Removing a deprecated method is `Update` (precedent: #5066 → #5392). Renaming an internal helper that nobody outside the plugin imports is `Dev`. When in doubt, prefer `Update`.

## readme.txt has a third location

Major or breaking changes also need a bullet under `== Compatibility Notes ==` in `readme.txt`. Required when:

- A method or class is removed
- A default behavior is flipped (e.g., a feature becomes enabled-by-default)
- Minimum WP/WC/PHP versions change

## Procedure

1. Locate the `xxxx-xx-xx - version X.Y.Z` block at the top of `changelog.txt`.
2. Append `* Type - Description` to the bottom of that version's block.
3. Locate the matching `= X.Y.Z - xxxx-xx-xx =` block in `readme.txt` under `== Changelog ==`.
4. Append the *exact same* `* Type - Description` line.
5. If the change is merchant-impacting, also add a bullet under `== Compatibility Notes ==` for that version in `readme.txt`.

## Wording conventions

- Describe behavior, not implementation.
  - Incorrect: Add `basename( WC_STRIPE_PLUGIN_PATH )` helper for slug derivation
  - Correct: Append a "See what's new" link to the plugin Updated! message after manual updates
- Lead with the verb: "Cancel pending WC Subscriptions retry when Stripe Radar blocks a renewal payment"
- One line per entry, no trailing period.
- If a related Linear issue or PR carries context, the *title* of the change is what ships — not the issue number.

## Verifying

After editing, search both files:

```bash
grep -n "your-text" changelog.txt readme.txt
```

If the change is also linked from the PR description, sync the wording so release notes and PR threads agree.

## Common mistakes

- Updating only one of the two files (the most common slip — both parsers expect to find the entry).
- Mixing the two version-header formats (silently breaks parsers).
- Classifying public-surface removals as `Dev` instead of `Update`.
- Adding a new version block when an `xxxx-xx-xx - version X.Y.Z` placeholder already exists at the top of `changelog.txt`.
