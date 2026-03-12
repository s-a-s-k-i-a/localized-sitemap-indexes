#!/bin/sh

set -eu

repo_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
plugin_slug="localized-sitemap-indexes"
plugin_file="$repo_root/$plugin_slug.php"
build_dir="$repo_root/build"

if [ ! -f "$plugin_file" ]; then
	echo "Plugin bootstrap file not found: $plugin_file" >&2
	exit 1
fi

version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$plugin_file" | head -n 1 | tr -d '\r')"

if [ -z "$version" ]; then
	echo "Could not determine plugin version from $plugin_file" >&2
	exit 1
fi

mkdir -p "$build_dir"

archive_path="$build_dir/$plugin_slug-$version.zip"

rm -f "$archive_path"

git -C "$repo_root" archive --format=zip --worktree-attributes --output="$archive_path" --prefix="$plugin_slug/" HEAD

echo "Created release archive: $archive_path"
