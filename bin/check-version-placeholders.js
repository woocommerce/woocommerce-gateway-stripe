#!/usr/bin/env node
/**
 * Checks the version tags a pull request adds against the `@since x.x.x`
 * convention.
 *
 * New PHP docblocks use `@since x.x.x` (or `@version x.x.x`), and woorelease
 * replaces the placeholder with the release version when the release is cut, in
 * the paths listed under `config.version_replace_paths` in package.json.
 * Nobody has to guess which release a change will ship in, so the tags can't go
 * stale when a pull request slips to a later release.
 *
 * Reports, for lines the pull request adds:
 * - error: an `x.x.x` that woorelease would not replace and would ship as is.
 *   woorelease only rewrites `@since` and `@version` followed by spaces, in
 *   `*.php` files under the configured paths.
 * - warning: a concrete `@since`/`@version` newer than the last released
 *   version, which should be `x.x.x` instead.
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
const RELEASED_HEADER = /^\d{4}-\d{2}-\d{2} - version (\d+\.\d+\.\d+)\s*$/m;
const CODE_FILE = /\.(?:php|jsx?|tsx?)$/i;
// Not shipped in the plugin zip (export-ignore), so a stray placeholder there is
// harmless; this script, its tests, and docs also mention x.x.x on purpose.
const UNSHIPPED_DIRS = ['bin/', 'tests/', 'docs/', '.github/', '.claude/', 'phpstan-stubs/', 'vendor/', 'node_modules/'];

/**
 * @param {string} file Repository-relative path.
 * @return {boolean} Whether the file is shipped code this check applies to.
 */
function isChecked(file) {
    return CODE_FILE.test(file) && !UNSHIPPED_DIRS.some((dir) => file.startsWith(dir));
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
 * @param {string} a Version like 11.1.0.
 * @param {string} b Version like 11.0.0.
 * @return {number} Positive when a is newer than b.
 */
function compareVersions(a, b) {
    const pa = a.split('.').map(Number);
    const pb = b.split('.').map(Number);
    for (let i = 0; i < 3; i++) {
        if (pa[i] !== pb[i]) {
            return pa[i] - pb[i];
        }
    }
    return 0;
}

/**
 * Lines added by a unified diff, with the file and new line number of each.
 *
 * @param {string} diff Output of `git diff --unified=0`.
 * @return {{file: string, line: number, text: string}[]} Added lines.
 */
function addedLines(diff) {
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
 * @param {string}   diff            Output of `git diff --unified=0`.
 * @param {string[]} replacePaths    Entries from version_replace_paths.
 * @param {string}   lastReleased    Latest released version, e.g. 11.0.0.
 * @return {{level: string, file: string, line: number, message: string}[]} Findings.
 */
function check(diff, replacePaths, lastReleased) {
    const findings = [];
    for (const { file, line, text } of addedLines(diff).filter(({ file }) => isChecked(file))) {
        // Every placeholder woorelease would not rewrite ships as a literal x.x.x.
        const total = text.split(PLACEHOLDER).length - 1;
        if (total > 0) {
            const replaceable = isReplaced(file, replacePaths) ? (text.match(REPLACEABLE_TAG) || []).length : 0;
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

        for (const [, tag, version] of text.matchAll(CONCRETE_TAG)) {
            if (compareVersions(version, lastReleased) > 0) {
                findings.push({
                    level: 'warning',
                    file,
                    line,
                    message: `@${tag} ${version} is not released yet. Use @${tag} x.x.x; woorelease replaces it with the version this ships in.`,
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
    const released = git('show', `${head}:changelog.txt`).match(RELEASED_HEADER);
    if (!released) {
        console.error('Could not find the latest released version header in changelog.txt.');
        process.exit(2);
    }

    const findings = check(diff, replacePaths, released[1]);
    for (const { level, file, line, message } of findings) {
        console.log(`::${level} file=${file},line=${line}::${message}`);
    }

    const errors = findings.filter((finding) => finding.level === 'error').length;
    console.log(`Version placeholders: ${errors} error(s), ${findings.length - errors} warning(s).`);
    process.exit(errors > 0 ? 1 : 0);
}

module.exports = { check, isChecked, isReplaced, addedLines };
