# Releasing

Releases are tag-driven. Pushing a tag `vX.Y.Z` runs
`.github/workflows/release.yml`, which re-runs the quality checks, verifies
version consistency, builds the plugin ZIP via `composer build:zip`, and
publishes a GitHub release with the ZIP attached and the matching
`CHANGELOG.md` section as release notes.

## Checklist

1. Update the version in all four places (they must match the tag):
   - `localized-sitemap-indexes.php` — `Version:` plugin header
   - `localized-sitemap-indexes.php` — `LOCALIZED_SITEMAP_INDEXES_VERSION` constant
   - `readme.txt` — `Stable tag:`
   - `README.md` — current development version
2. Move the `Unreleased` items in `CHANGELOG.md` into a new
   `## X.Y.Z - YYYY-MM-DD` section and add a user-facing summary to the
   `== Changelog ==` section in `readme.txt`.
3. Run the checks and a local test build:

   ```bash
   composer check
   composer build:zip
   ```

4. Commit, push, and confirm CI is green on `main`.
5. Tag and push the tag:

   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```

6. Verify the release on GitHub: the release page must contain the changelog
   excerpt and the installable `localized-sitemap-indexes-X.Y.Z.zip`.

## Notes

- The release workflow fails on purpose if the tag does not match the plugin
  header, the version constant, or the readme stable tag.
- A tag containing a hyphen (for example `v0.3.0-beta.1`) is published as a
  prerelease.
- The ZIP is built with `git archive`, so everything marked `export-ignore`
  in `.gitattributes` stays out of the shipped plugin. New development-only
  files must be added there.
