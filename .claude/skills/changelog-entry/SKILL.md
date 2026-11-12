---
name: changelog-entry
description: Use when adding or editing changelog entries for this plugin. Triggers include "add changelog", "changelog entry", "release notes", "update changelog.txt", "update readme.txt", or any PR that introduces user-visible behavior changes. Critical: entries are added as files under changelog/ with `npm run changelog`; changelog.txt and readme.txt are compiled at release time and must not be edited in feature PRs.
---

# Changelog Entry — WC Stripe Gateway

Adds a changelog entry as its own file under `changelog/`, with the right entry type. The entry carries no version: it is compiled into `changelog.txt` and `readme.txt` during the release process.

## Critical: add an entry file, do not edit the compiled files

```bash
npm run changelog -- --type=fix --entry="Describe the change"
```

This runs the Jetpack Changelogger and creates a file named after the current branch in `changelog/`:

```
Significance: patch
Type: fix

Describe the change
```

Commit that file with the change. Run `npm run changelog` with no arguments for interactive prompts. Requires `composer install`.

- **Do not edit `changelog.txt` or the `== Changelog ==` section of `readme.txt`.** The `Validate Changelog` check rejects those edits; both are written by `npm run changelog:build-file` at release time.
- A PR that needs more than one entry runs the command once per entry.
- To reword an entry, edit the text in its file. `Significance` is always `patch`; it is required by the file format but unused, because the release version is set when the changelog is compiled.
- `changelog/.gitkeep` keeps the directory in git once a release has compiled the entries. Never delete it.

## Compiling for a release

Release owners run the **Release Changelog** workflow (or `npm run changelog:build-file -- --version=X.Y.Z` locally) on the release branch. It writes the entries to both files, with the header format each one needs, and deletes the entry files:

- `changelog.txt` (WooCommerce.com parser): `xxxx-xx-xx - version X.Y.Z`
- `readme.txt` (WordPress.org parser): `= X.Y.Z - xxxx-xx-xx =`

The `xxxx-xx-xx` date is deliberate: woorelease replaces it at release and fails without it. Running the command again for the same version appends newly landed entries to that section. `readme.txt` only carries the current release; `changelog.txt` holds the full history.

## Entry types

| Type | When to use |
|------|-------------|
| **Add** | New feature or capability merchants can use |
| **Update** | Behavior change visible to merchants — including removing public-surface methods that extension authors may have relied on |
| **Fix** | Bug fix |
| **Remove** | Removal of merchant-facing functionality |
| **Tweak** | Minor visual/copy change that doesn't fit Add/Update/Fix |
| **Dev** | Internal-only change with no merchant or extension-author impact (CI, test refactors, dev dependencies) |

**The Add/Update vs Dev judgment:** if the change can break someone else's site or workflow when they update, it's `Update`, not `Dev`. Removing a deprecated method is `Update` (precedent: #5066 → #5392). Renaming an internal helper that nobody outside the plugin imports is `Dev`. When in doubt, prefer `Update`.

## readme.txt Compatibility Notes

Major or breaking changes also need a bullet under `== Compatibility Notes ==` in `readme.txt`, edited by hand in the same PR (this section is not compiled). Required when:

- A method or class is removed
- A default behavior is flipped (e.g., a feature becomes enabled-by-default)
- Minimum WP/WC/PHP versions change

## Procedure

1. Pick the entry type from the table above.
2. Run `npm run changelog -- --type=<type> --entry="<description>"`.
3. Commit the new file under `changelog/`.
4. If the change is merchant-impacting, also add a bullet under `== Compatibility Notes ==` in `readme.txt`.

## Wording conventions

- Describe behavior, not implementation.
  - Incorrect: Add `basename( WC_STRIPE_PLUGIN_PATH )` helper for slug derivation
  - Correct: Append a "See what's new" link to the plugin Updated! message after manual updates
- Lead with the verb: "Cancel pending WC Subscriptions retry when Stripe Radar blocks a renewal payment"
- One line per entry, no trailing period.
- If a related Linear issue or PR carries context, the *title* of the change is what ships — not the issue number.

## Verifying

```bash
vendor/bin/changelogger validate
git status --short changelog/
```

If the change is also linked from the PR description, sync the wording so release notes and PR threads agree.

## Common mistakes

- Editing `changelog.txt` or the `readme.txt` changelog section directly instead of adding an entry file.
- Forgetting to commit the new file under `changelog/`.
- Classifying public-surface removals as `Dev` instead of `Update`.
- Adding an `xxxx-xx-xx` version block by hand; `npm run changelog:build-file` creates it.
