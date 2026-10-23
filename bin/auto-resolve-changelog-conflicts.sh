#!/usr/bin/env bash
#
# Merges origin/develop into one PR branch when the only conflicts are in
# changelog.txt/readme.txt, resolves them with resolve-changelog-conflicts.js,
# and pushes the merge commit. Any other conflict is left for a person (exit 0).
#
# Usage: bin/auto-resolve-changelog-conflicts.sh <pr-number> <head-branch>
#   RESOLVER  path to the resolver (default: next to this script; the workflow
#             points it at a develop checkout so no PR code runs).
#   DRY_RUN=1 resolve and commit locally without pushing.

set -euo pipefail

number="$1"
head_ref="$2"
resolver="${RESOLVER:-$(dirname "$0")/resolve-changelog-conflicts.js}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# The branch name goes into refspecs, so reject anything but a plain branch name.
git check-ref-format --branch "$head_ref" > /dev/null

# Drop any half-done merge an earlier run left in this clone.
git merge --abort > /dev/null 2>&1 || true

git fetch --quiet origin \
	"+refs/heads/develop:refs/remotes/origin/develop" \
	"+refs/heads/${head_ref}:refs/remotes/origin/${head_ref}"
git checkout --quiet --force --detach "origin/${head_ref}"

if git merge --no-edit --no-commit origin/develop > /dev/null 2>&1; then
	# A clean merge is not this script's job; leave the branch alone.
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

# Never force: a rejected push (the author pushed meanwhile) is retried next run.
if git push --quiet origin "HEAD:refs/heads/${head_ref}"; then
	echo "#${number}: resolved ${files} and pushed."
else
	echo "::warning::#${number}: push to ${head_ref} was rejected; it will be retried on the next run."
fi
