#!/usr/bin/env node
/**
 * Ensures that code uses `@since x.x.x` instead of concrete versions.
 *
 * New PHP docblocks must use `@since x.x.x` (or `@version x.x.x`), with
 * the version replace due to happen during the release process for 
 * files under `config.version_replace_paths` in package.json
 *
 * Reports, for lines the pull request adds, both as errors:
 * - an `x.x.x` that cannot be replaced and would ship as is.
 * - a concrete `@since`/`@version`, which must be `x.x.x` so the release
 *   process fills in the version.
 *
 * Usage:
 *   node bin/check-version-placeholders.js <base-ref> [<head-ref>]
 *
 * Prints GitHub Actions annotations and exits with status 1 when there are errors.
 */
const { execFileSync } = require('child_process');
const fs = require('fs');

const PLACEHOLDER = 'x.x.x';
// Mirrors the sed pattern in woorelease's Version_Replace: one or more spaces, not tabs.
const REPLACEABLE_TAG = /@(?:since|version) +x\.x\.x/g;
const CONCRETE_TAG = /@(since|version)\s+(\d+\.\d+\.\d+)/g;
const CODE_FILE = /\.(?:php|jsx?|tsx?)$/i;
// Source folders that ship in the plugin zip. An include list, so new unshipped
// folders (docker/, release/, ...) are skipped without editing it. Files outside
// it can mention x.x.x on purpose, like this script and its tests.
const SHIPPED_DIRS = ['includes/', 'templates/', 'client/', 'assets/js/'];
// Root PHP files ship too. Checking all of them catches a new one that is
// missing from version_replace_paths.
const ROOT_PHP_FILE = /^[^/]+\.php$/i;

/**
 * @param {string} file Repository-relative path.
 * @return {boolean} Whether the file is shipped code this check applies to.
 */
function isChecked(file) {
    return ROOT_PHP_FILE.test(file) || (CODE_FILE.test(file) && SHIPPED_DIRS.some((dir) => file.startsWith(dir)));
}

/**
 * Whether woorelease would process this file, given version_replace_paths.
 * A directory entry covers every `*.php` file below it (case-insensitive, like
 * `find -iname`); a file entry covers that file only.
 *
 * @param {string}   file         Repository-relative path.
 * @param {string[]} replacePaths Entries from version_replace_paths.
 * @return {boolean} Whether placeholders in the file are replaced at release.
 */
function isReplaced(file, replacePaths) {
    return replacePaths.some((entry) => {
        const path = entry.replace(/^\.\//, '').replace(/\/+$/, '');
        if (file === path) {
            return true;
        }
        return file.startsWith(`${path}/`) && /\.php$/i.test(file);
    });
}

/**
 * Extract lines that were added from a unified diff, with the file and new line number of each.
 *
 * @param {string} diff Output of `git diff --unified=0`.
 * @return {{file: string, line: number, text: string}[]} Added lines.
 */
function extractAddedLines(diff) {
    const lines = [];
    let file = null;
    let line = 0;
    for (const raw of diff.split('\n')) {
        if (raw.startsWith('+++ ')) {
            file = raw === '+++ /dev/null' ? null : raw.replace(/^\+\+\+ b\//, '');
            continue;
        }
        const hunk = raw.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/);
        if (hunk) {
            line = Number(hunk[1]);
            continue;
        }
        if (file && raw.startsWith('+')) {
            lines.push({ file, line, text: raw.slice(1) });
            line++;
        }
    }
    return lines;
}

/**
 * Count the concrete `@since`/`@version` tags on removed lines, per file of the new side.
 *
 * @param {string} diff Output of `git diff --unified=0`.
 * @return {Map<string, Map<string, number>>} For each file, how often each tag (e.g. `@since 10.8.0`) was removed.
 */
function countRemovedConcreteTags(diff) {
    const counts = new Map();
    let file = null;
    for (const raw of diff.split('\n')) {
        if (raw.startsWith('--- ')) {
            continue;
        }
        if (raw.startsWith('+++ ')) {
            file = raw === '+++ /dev/null' ? null : raw.replace(/^\+\+\+ b\//, '');
            continue;
        }
        if (!file || !raw.startsWith('-')) {
            continue;
        }
        for (const [, tag, version] of raw.slice(1).matchAll(CONCRETE_TAG)) {
            const tags = counts.get(file) || new Map();
            const key = `@${tag} ${version}`;
            tags.set(key, (tags.get(key) || 0) + 1);
            counts.set(file, tags);
        }
    }
    return counts;
}

/**
 * @param {string}   diff            Output of `git diff --unified=0`.
 * @param {string[]} replacePaths    Entries from version_replace_paths.
 * @return {{level: string, file: string, line: number, message: string}[]} Findings.
 */
function check(diff, replacePaths) {
    const findings = [];
    const addedLinesInCheckedFiles = extractAddedLines(diff).filter(({ file }) => isChecked(file));
    // A docblock that is moved or re-indented shows up as removed and added lines. Its tag keeps
    // its real version, so a concrete tag the same file also removes is not new.
    const removedTags = countRemovedConcreteTags(diff);
    for (const { file, line, text } of addedLinesInCheckedFiles) {
        const replaced = isReplaced(file, replacePaths);

        // Every placeholder woorelease would not rewrite ships as a literal x.x.x.
        const total = text.split(PLACEHOLDER).length - 1;
        if (total > 0) {
            const replaceable = replaced ? (text.match(REPLACEABLE_TAG) || []).length : 0;
            if (total > replaceable) {
                findings.push({
                    level: 'error',
                    file,
                    line,
                    message:
                        'This x.x.x will not be replaced at release. woorelease only replaces `@since x.x.x` and ' +
                        '`@version x.x.x` (with spaces) in PHP files under config.version_replace_paths in ' +
                        'package.json. Use a concrete version here, e.g. for @deprecated and deprecation function arguments.',
                });
            }
        }

        // New code should use the placeholder so the release process fills in
        // the version. Only where woorelease replaces x.x.x, though: elsewhere
        // (JS, files outside the replace paths) x.x.x would not be rewritten, so
        // a concrete version is correct there and the literal check above guards
        // x.x.x instead.
        if (replaced) {
            for (const [, tag, version] of text.matchAll(CONCRETE_TAG)) {
                const tags = removedTags.get(file);
                const key = `@${tag} ${version}`;
                if (tags && tags.get(key) > 0) {
                    tags.set(key, tags.get(key) - 1);
                    continue;
                }
                findings.push({
                    level: 'error',
                    file,
                    line,
                    message: `@${tag} ${version} is a concrete version. Use @${tag} x.x.x so it will get updated during the release process.`,
                });
            }
        }
    }
    return findings;
}

if (require.main === module) {
    const [base, head = 'HEAD'] = process.argv.slice(2);
    if (!base) {
        console.error('Usage: node bin/check-version-placeholders.js <base-ref> [<head-ref>]');
        process.exit(2);
    }

    const git = (...args) => execFileSync('git', args, { encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 });
    const mergeBase = git('merge-base', base, head).trim();
    const diff = git('diff', '--unified=0', '--no-color', '--no-ext-diff', mergeBase, head);

    // woorelease reads its settings from a `woorelease` key when present, else `config`.
    const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
    const replacePaths = [].concat((pkg.woorelease || pkg.config || {}).version_replace_paths || []);

    const findings = check(diff, replacePaths);
    for (const { level, file, line, message } of findings) {
        console.log(`::${level} file=${file},line=${line}::${message}`);
    }

    console.log(`Version placeholders: ${findings.length} error(s).`);
    process.exit(findings.length > 0 ? 1 : 0);
}

module.exports = { check, isChecked, isReplaced, extractAddedLines };
