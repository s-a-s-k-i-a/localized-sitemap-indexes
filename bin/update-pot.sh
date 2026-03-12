#!/bin/sh

set -eu

repo_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
pot_file="$repo_root/languages/localized-sitemap-indexes.pot"

if ! command -v wp >/dev/null 2>&1; then
	echo "WP-CLI with the i18n command is required to regenerate $pot_file" >&2
	exit 1
fi

wp i18n make-pot "$repo_root" "$pot_file" \
	--slug=localized-sitemap-indexes \
	--domain=localized-sitemap-indexes \
	--exclude=vendor

echo "Updated translation template: $pot_file"
