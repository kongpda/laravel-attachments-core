# Changelog

## Unreleased

## 0.2.0 - 2026-09-21

### Security

- Every file is served through the authorised download route by default, with
  `nosniff`, `Cache-Control: private`, and inline display only for images and
  PDFs. Signed and direct URLs are opt-in per disk.
- `uploads.allowed_mimes` now defaults to a safe list, matched against sniffed
  content.
- Thumbnails are skipped for images that declare more pixels than
  `thumbnails.max_source_pixels`.
- The upload disk comes from code, never from request input. Path, disk,
  morph and uploader columns are guarded against mass assignment.
- Serve routes use the `web` and `auth` middleware and are throttled.

### Added

- `attachments:prune` force-deletes attachments trashed longer than
  `prune.after_days`, together with their files.
- ETag and `304 Not Modified` on downloads and thumbnails. Authorisation still
  runs first.
- Support for Laravel 13 and PHP 8.5.

### Changed

- Requires PHP 8.4 and Laravel 12 or 13.
- Thumbnails are queued by default.
- `storage.path_prefixes` is now applied. New uploads go under `attachments/`
  unless configured otherwise. Existing files are not moved.
- `intervention/image-laravel` has been replaced by `intervention/image`
  (3 or 4), because the bridge conflicts with Laravel 13's native image
  support.
- The serve action reads the MIME type from the record, instead of querying
  storage for it.

### Fixed

- Queued thumbnails were generated but never recorded.

## 0.1.0 - 2026-04-05

- Initial release.
