# Releasing

1. Make sure CI is green on `main`.
2. Move the "Unreleased" notes in `CHANGELOG.md` under the new version.
3. Tag the release, for example `git tag v0.2.0 && git push origin v0.2.0`.
4. Packagist updates from the GitHub webhook. Check that the new version is
   listed.

Follow semantic versioning. While the version is 0.x, a minor release may
break the public API. Say so in the changelog when it does.
