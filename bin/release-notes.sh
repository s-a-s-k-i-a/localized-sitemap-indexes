#!/bin/sh

set -eu

version="${1:?Usage: release-notes.sh <version>}"
repo_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
changelog="$repo_root/CHANGELOG.md"

if [ ! -f "$changelog" ]; then
	echo "Changelog not found: $changelog" >&2
	exit 1
fi

notes="$(awk -v version="$version" '
	/^## / {
		if (found) {
			exit
		}
		if ($2 == version) {
			found = 1
		}
		next
	}
	found { print }
' "$changelog")"

if [ -z "$(printf '%s' "$notes" | tr -d '[:space:]')" ]; then
	echo "No changelog section found for version $version in $changelog" >&2
	exit 1
fi

printf '%s\n' "$notes"
