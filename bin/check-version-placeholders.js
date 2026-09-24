#!/usr/bin/env node
/**
 * Checks version placeholders against what woorelease replaces at release.
 *
 * New code uses `x.x.x` instead of guessing the release it will ship in:
 * `@since x.x.x`, `@deprecated x.x.x`, and `'x.x.x'` as the version argument of
 * deprecation functions. woorelease replaces them with the release version when
 * the release is cut, in the paths listed under `config.version_replace_paths`
 * in package.json. Quoted `'x.x.x'` strings are only replaced when
 * `config.version_replace_strings` is true. Nobody has to guess, so versions
 * can't go stale when a pull request slips to a later release.
 *
 * Pull request mode reports, for lines the pull request adds to shipped code:
 * - error: an `x.x.x` that woorelease would not replace and would ship as is;
 * - warning: a concrete `@since`/`@version`/`@deprecated` or deprecation
 *   function version newer than the last released version, which should be
 *   `x.x.x` instead.
 *
 * Tree mode reports every `x.x.x` left in shipped code at a ref, e.g. a release
 * tag, which means the release shipped a placeholder.
 *
 * Usage:
 *   node bin/check-version-placeholders.js <base-ref> [<head-ref>]
 *   node bin/check-version-placeholders.js --tree [<ref>]
 *
 * Prints GitHub Actions annotations and exits with status 1 when there are errors.
 */
const { execFileSync } = require('child_process');
const fs = require('fs');

const PLACEHOLDER = 'x.x.x';
// Mirrors woorelease's Version_Replace: the tag, one or more spaces (not tabs), x.x.x.
const REPLACEABLE_TAG = /@(?:since|version|deprecated) +x\.x\.x/g;
// Replaced only with version_replace_strings, and only as an exact quoted string.
const REPLACEABLE_STRING = /'x\.x\.x'|"x\.x\.x"/g;
const CONCRETE_TAG = /@(since|version|deprecated)\s+(\d+\.\d+\.\d+)/g;
const DEPRECATION_CALL =
    /\b(?:_deprecated_(?:function|hook|argument|file|constructor|class)|wc_deprecated_(?:function|hook|argument)|apply_filters_deprecated|do_action_deprecated)\s*\(/;
const QUOTED_VERSION = /(['"])(\d+\.\d+\.\d+)\1/g;
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

const countOf = (text, pattern) => (text.match(pattern) || []).length;

/**
 * @param {string} diff   Output of `git diff --unified=0`.
 * @param {{replacePaths: string[], replaceStrings: boolean}} config woorelease settings.
 * @param {string} lastReleased Latest released version, e.g. 11.0.0.
 * @return {{level: string, file: string, line: number, message: string}[]} Findings.
 */
function check(diff, { replacePaths, replaceStrings }, lastReleased) {
    const replaced = replaceStrings
        ? '`@since`, `@version` and `@deprecated x.x.x` (with spaces) and quoted `\'x.x.x\'` strings'
        : '`@since`, `@version` and `@deprecated x.x.x` (with spaces)';
    const findings = [];
    // Open parentheses of a deprecation call that spans consecutive added lines,
    // so its version argument is found even when it sits on its own line.
    let callDepth = 0;
    let previous = null;

    for (const current of addedLines(diff).filter(({ file }) => isChecked(file))) {
        const { file, line, text } = current;
        if (!previous || previous.file !== file || previous.line + 1 !== line) {
            callDepth = 0;
        }
        previous = current;

        // Every placeholder woorelease would not rewrite ships as a literal x.x.x.
        const total = text.split(PLACEHOLDER).length - 1;
        if (total > 0) {
            let replaceable = 0;
            if (isReplaced(file, replacePaths)) {
                replaceable = countOf(text, REPLACEABLE_TAG) + (replaceStrings ? countOf(text, REPLACEABLE_STRING) : 0);
            }
            if (total > replaceable) {
                findings.push({
                    level: 'error',
                    file,
                    line,
                    message: `This x.x.x will not be replaced at release. woorelease only replaces ${replaced} in PHP files under config.version_replace_paths in package.json.`,
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

        const call = text.match(DEPRECATION_CALL);
        if (call) {
            callDepth = 0;
        }
        if (call || callDepth > 0) {
            const rest = call ? text.slice(call.index) : text;
            for (const [, , version] of rest.matchAll(QUOTED_VERSION)) {
                if (compareVersions(version, lastReleased) > 0) {
                    findings.push({
                        level: 'warning',
                        file,
                        line,
                        message: replaceStrings
                            ? `Deprecation version ${version} is not released yet. Use 'x.x.x'; woorelease replaces it with the version this ships in.`
                            : `Deprecation version ${version} is not released yet; make sure it matches the release this ships in.`,
                    });
                }
            }
            callDepth += countOf(rest, /\(/g) - countOf(rest, /\)/g);
        }
    }
    return findings;
}

/**
 * Every placeholder left in shipped code, e.g. at a release tag.
 *
 * @param {{file: string, line: number, text: string}[]} lines Lines containing x.x.x.
 * @param {string} ref The ref that was searched.
 * @return {{level: string, file: string, line: number, message: string}[]} Findings.
 */
function checkTree(lines, ref) {
    return lines
        .filter(({ file, text }) => isChecked(file) && text.includes(PLACEHOLDER))
        .map(({ file, line }) => ({
            level: 'error',
            file,
            line,
            message: `${ref} ships an unreplaced x.x.x. Was the release cut with a woorelease that replaces it, and is this path in config.version_replace_paths?`,
        }));
}

if (require.main === module) {
    const git = (...args) => execFileSync('git', args, { encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 });
    const report = (findings) => {
        for (const { level, file, line, message } of findings) {
            console.log(`::${level} file=${file},line=${line}::${message}`);
        }
        const errors = findings.filter((finding) => finding.level === 'error').length;
        console.log(`Version placeholders: ${errors} error(s), ${findings.length - errors} warning(s).`);
        process.exit(errors > 0 ? 1 : 0);
    };

    const args = process.argv.slice(2);
    if (args[0] === '--tree') {
        const ref = args[1] || 'HEAD';
        let output = '';
        try {
            output = git('grep', '-nF', '-e', PLACEHOLDER, ref, '--');
        } catch (error) {
            // git grep exits 1 when nothing matches.
            if (error.status !== 1) {
                throw error;
            }
        }
        const lines = output
            .split('\n')
            .map((row) => row.slice(ref.length + 1).match(/^(.+?):(\d+):(.*)$/))
            .filter(Boolean)
            .map(([, file, line, text]) => ({ file, line: Number(line), text }));
        report(checkTree(lines, ref));
    }

    const [base, head = 'HEAD'] = args;
    if (!base) {
        console.error('Usage: node bin/check-version-placeholders.js <base-ref> [<head-ref>] | --tree [<ref>]');
        process.exit(2);
    }

    let mergeBase;
    try {
        mergeBase = git('merge-base', base, head).trim();
    } catch (error) {
        console.error(`Could not find a merge base for "${base}" and "${head}"; are both valid refs?`);
        process.exit(2);
    }
    const diff = git('diff', '--unified=0', '--no-color', '--no-ext-diff', mergeBase, head);

    // woorelease reads its settings from a `woorelease` key when present, else
    // `config`, and turns on string replacement for any non-empty value.
    const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
    const settings = pkg.woorelease || pkg.config || {};
    const config = {
        replacePaths: [].concat(settings.version_replace_paths || []),
        replaceStrings: Boolean(settings.version_replace_strings),
    };

    const released = git('show', `${head}:changelog.txt`).match(RELEASED_HEADER);
    if (!released) {
        console.error('Could not find the latest released version header in changelog.txt.');
        process.exit(2);
    }

    report(check(diff, config, released[1]));
}

module.exports = { check, checkTree, isChecked, isReplaced, addedLines };
