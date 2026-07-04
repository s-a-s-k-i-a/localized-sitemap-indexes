#!/bin/sh

set -eu

repo_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
plugin_slug="localized-sitemap-indexes"
plugin_file="$repo_root/$plugin_slug.php"
build_dir="$repo_root/build"
stage_root="$build_dir/stage"
stage_dir="$stage_root/$plugin_slug"

if [ ! -f "$plugin_file" ]; then
	echo "Plugin bootstrap file not found: $plugin_file" >&2
	exit 1
fi

version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$plugin_file" | head -n 1 | tr -d '\r')"

if [ -z "$version" ]; then
	echo "Could not determine plugin version from $plugin_file" >&2
	exit 1
fi

archive_path="$build_dir/$plugin_slug-$version.zip"

rm -rf "$stage_root"
rm -f "$archive_path"
mkdir -p "$stage_dir"

# Stage the plugin files from HEAD; the export-ignore rules in .gitattributes
# decide what ships. Production Composer dependencies are then installed into
# the stage so the ZIP contains vendor/ without touching the development
# vendor directory of the checkout.
git -C "$repo_root" archive --format=tar --worktree-attributes HEAD | tar -x -C "$stage_dir"

cp "$repo_root/composer.json" "$repo_root/composer.lock" "$stage_dir/"
composer install \
	--working-dir="$stage_dir" \
	--no-dev \
	--optimize-autoloader \
	--no-interaction \
	--quiet
rm -f "$stage_dir/composer.json" "$stage_dir/composer.lock"

(
	cd "$stage_root"
	zip -rq "$archive_path" "$plugin_slug"
)

rm -rf "$stage_root"

echo "Created release archive: $archive_path"
