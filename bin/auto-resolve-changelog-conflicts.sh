#!/usr/bin/env bash
#
# Merges origin/develop into one pull request branch when the only conflicts are
# in changelog.txt and/or readme.txt, resolves them with
# resolve-changelog-conflicts.js, and pushes the merge commit to the branch.
# Any other conflict, or a changelog change the resolver refuses, is left for a
# person and exits 0.
#
# Usage (from inside a clone whose origin is the repository):
#   bin/auto-resolve-changelog-conflicts.sh <pr-number> <head-branch>
#
# Environment:
#   RESOLVER  Path to resolve-changelog-conflicts.js. Defaults to the copy next
#             to this script; the workflow points it at a develop checkout so
#             no code from the pull request runs.
#   DRY_RUN   Set to 1 to resolve and commit locally without pushing.

set -euo pipefail

number="$1"
head_ref="$2"
resolver="${RESOLVER:-$(dirname "$0")/resolve-changelog-conflicts.js}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# The branch name ends up in refspecs; reject anything that isn't a plain branch name.
git check-ref-format --branch "$head_ref" > /dev/null

# The workflow runs this once per pull request in the same clone; drop anything
# an earlier run left behind.
git merge --abort > /dev/null 2>&1 || true

git fetch --quiet origin \
	"+refs/heads/develop:refs/remotes/origin/develop" \
	"+refs/heads/${head_ref}:refs/remotes/origin/${head_ref}"
git checkout --quiet --force --detach "origin/${head_ref}"

if git merge --no-edit --no-commit origin/develop > /dev/null 2>&1; then
	# Merging cleanly is not this script's concern; leave the branch alone.
	git merge --abort
	echo "#${number}: no conflicts with develop."
	exit 0
fi

conflicted="$(git diff --name-only --diff-filter=U)"
if [ -z "$conflicted" ] || grep -qvxE 'changelog\.txt|readme\.txt' <<< "$conflicted"; then
	git merge --abort
	echo "#${number}: conflicts outside changelog.txt/readme.txt; leaving for a person:"
	echo "$conflicted"
	exit 0
fi

merge_base="$(git merge-base HEAD origin/develop)"
for file in $conflicted; do
	git show "${merge_base}:${file}" > "$tmp/merge-base"
	git show "HEAD:${file}" > "$tmp/pr"
	git show "origin/develop:${file}" > "$tmp/develop"

	if ! node "$resolver" "$tmp/merge-base" "$tmp/pr" "$tmp/develop" > "$tmp/resolved"; then
		git merge --abort
		echo "#${number}: could not auto-resolve ${file}; leaving for a person."
		exit 0
	fi

	cp "$tmp/resolved" "$file"
	git add "$file"
done

files="$(paste -sd ' ' - <<< "$conflicted")"
git commit --quiet \
	-m "Merge branch 'develop' into ${head_ref}" \
	-m "Auto-resolved conflicts in: ${files}"

if [ "${DRY_RUN:-}" = 1 ]; then
	echo "#${number}: resolved ${files} (dry run, not pushed)."
	exit 0
fi

# Never force: if the author pushed meanwhile, the push is rejected and the next
# run (on their push, or the next develop push) tries again.
if git push --quiet origin "HEAD:refs/heads/${head_ref}"; then
	echo "#${number}: resolved ${files} and pushed."
else
	echo "::warning::#${number}: push to ${head_ref} was rejected; it will be retried on the next run."
fi
