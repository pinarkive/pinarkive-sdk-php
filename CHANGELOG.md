# Changelog

All notable changes to `pinarkive/pinarkive-sdk-php` are documented here.

## [3.1.1] - 2026-04-14

### Fixed

- **`uploadDirectoryDAG` multipart format:** The API expects multer field **`files`** (repeated), with each part’s **filename** set to the relative path inside the DAG. The SDK previously sent `files[i][path]` / `files[i][content]`, which the backend does not parse into `req.files`.

### Release / publish

This SDK repo does not currently include a documented automated release workflow in this monorepo copy. If the upstream repo uses Packagist, a GitHub Release, or tag-based automation, publish `3.1.1` following that repo’s release process after merging and tagging.

