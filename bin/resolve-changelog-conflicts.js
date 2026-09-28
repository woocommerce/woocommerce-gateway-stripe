#!/usr/bin/env node
/**
 * Resolves a merge conflict in changelog.txt or readme.txt.
 *
 * The result is the base branch's version of the file with the pull request's
 * new changelog entries appended to its upcoming (xxxx-xx-xx) version section.
 * Taking the base branch's file whole, instead of resolving conflict hunks, also
 * moves an entry out of a section that was released after the branch was cut.
 *
 * Usage:
 *   node bin/resolve-changelog-conflicts.js <merge-base-file> <pr-file> <base-branch-file>
 *
 * Prints the resolved file to stdout. Exits with status 1 when the pull request
 * changed anything other than adding entries (removed or edited lines, version
 * headers, readme metadata such as "Tested up to"), so a person resolves those.
 *
 * Deliberately dependency-free: the auto-resolve workflow runs it with a
 * write-scoped token and must not install packages or run pull request code.
 */
const fs = require('fs');

// The same two section formats bin/changelog.js writes to:
// - changelog.txt: xxxx-xx-xx - version X.Y.Z
// - readme.txt:    = X.Y.Z - xxxx-xx-xx =
const UPCOMING_HEADER = /^(?:xxxx-xx-xx\s*-\s*version\s+\d+\.\d+\.\d+|=\s*\d+\.\d+\.\d+\s*-\s*xxxx-xx-xx\s*=)\s*$/;
// Any version header, released or upcoming, in either format.
const ANY_HEADER = /^(?:[0-9x]{4}-[0-9x]{2}-[0-9x]{2}\s*-\s*version\s+\d+\.\d+\.\d+|=\s*\d+\.\d+\.\d+\s*-\s*[0-9x]{4}-[0-9x]{2}-[0-9x]{2}\s*=)\s*$/;
const ENTRY = /^\* \S+ - \S/;

class UnresolvableError extends Error {}

const isBlank = (line) => line.trim() === '';

/**
 * Lines added and removed going from one version to another, compared as
 * multisets so moved lines do not count as changes. Blank lines are ignored.
 *
 * @param {string[]} fromLines Original lines.
 * @param {string[]} toLines   Changed lines.
 * @return {{added: string[], removed: string[]}} Added lines in toLines order, and removed lines.
 */
function diffLines(fromLines, toLines) {
    const remaining = new Map();
    for (const line of fromLines) {
        if (!isBlank(line)) {
            remaining.set(line, (remaining.get(line) || 0) + 1);
        }
    }

    const added = [];
    for (const line of toLines) {
        if (isBlank(line)) {
            continue;
        }
        const count = remaining.get(line) || 0;
        if (count > 0) {
            remaining.set(line, count - 1);
        } else {
            added.push(line);
        }
    }

    const removed = [];
    for (const [line, count] of remaining) {
        for (let i = 0; i < count; i++) {
            removed.push(line);
        }
    }

    return { added, removed };
}

/**
 * @param {string} mergeBase  File content at the merge base.
 * @param {string} pr         File content on the pull request branch.
 * @param {string} baseBranch File content on the base branch (develop).
 * @return {string} Resolved file content.
 * @throws {UnresolvableError} When the change can't be resolved safely.
 */
function resolve(mergeBase, pr, baseBranch) {
    const { added, removed } = diffLines(mergeBase.split('\n'), pr.split('\n'));

    if (removed.length > 0) {
        throw new UnresolvableError(`the pull request removed or edited an existing line: ${removed[0]}`);
    }

    const nonEntry = added.find((line) => !ENTRY.test(line));
    if (nonEntry !== undefined) {
        throw new UnresolvableError(`the pull request added a line that is not a changelog entry: ${nonEntry}`);
    }

    const lines = baseBranch.split('\n');
    const start = lines.findIndex((line) => UPCOMING_HEADER.test(line));
    if (start === -1) {
        throw new UnresolvableError('the base branch has no upcoming (xxxx-xx-xx) version section');
    }

    let end = lines.length;
    for (let i = start + 1; i < lines.length; i++) {
        if (ANY_HEADER.test(lines[i])) {
            end = i;
            break;
        }
    }

    // After the section's last entry, like bin/changelog.js, so trailing text
    // such as readme.txt's "See changelog" link stays last.
    let insertAt = start + 1;
    for (let i = start + 1; i < end; i++) {
        if (ENTRY.test(lines[i])) {
            insertAt = i + 1;
        }
    }

    // An entry can already be on the base branch, e.g. when it was cherry-picked.
    const present = new Set(lines);
    const toInsert = [...new Set(added)].filter((line) => !present.has(line));

    lines.splice(insertAt, 0, ...toInsert);
    return lines.join('\n');
}

if (require.main === module) {
    const files = process.argv.slice(2);
    if (files.length !== 3) {
        console.error('Usage: node bin/resolve-changelog-conflicts.js <merge-base-file> <pr-file> <base-branch-file>');
        process.exit(2);
    }

    const [mergeBase, pr, baseBranch] = files.map((file) => fs.readFileSync(file, 'utf8'));
    try {
        process.stdout.write(resolve(mergeBase, pr, baseBranch));
    } catch (error) {
        if (!(error instanceof UnresolvableError)) {
            throw error;
        }
        console.error(`Cannot auto-resolve: ${error.message}`);
        process.exit(1);
    }
}

module.exports = { resolve, UnresolvableError };
