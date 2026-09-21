# Laravel Attachments Core

[![Tests](https://github.com/kongpda/laravel-attachments-core/actions/workflows/run-tests.yml/badge.svg)](https://github.com/kongpda/laravel-attachments-core/actions/workflows/run-tests.yml)

Polymorphic file attachments for Laravel: upload, store, authorise, serve and
thumbnail files on any model, on any filesystem disk (local, S3, Cloudflare R2).

This package is the backend only. For UI, pair it with:

- [`kongpda/laravel-attachments-livewire`](https://github.com/kongpda/laravel-attachments-livewire) for Blade and Livewire
- [`@kongpda/laravel-attachments-react`](https://github.com/kongpda/laravel-attachments-react) for React and Inertia

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- The GD extension (thumbnails)

## Installation

```bash
composer require kongpda/laravel-attachments-core
php artisan vendor:publish --tag=attachments-core-migrations
php artisan migrate
```

Publish the config if you need to change a default:

```bash
php artisan vendor:publish --tag=attachments-core-config
```

Schedule the prune command (see [Deleting](#deleting)):

```php
// routes/console.php
Schedule::command('attachments:prune')->daily();
```

## Usage

Add the trait to any model that owns files:

```php
use Kongpda\LaravelAttachments\Models\Concerns\HasAttachments;

class Invoice extends Model
{
    use HasAttachments;
}
```

Upload with the action. It checks the size and the *sniffed* MIME type, stores
the file, records it, and generates a thumbnail for images:

```php
use Kongpda\LaravelAttachments\Actions\UploadAttachment;

public function store(Request $request, Invoice $invoice, UploadAttachment $upload)
{
    $this->authorize('update', $invoice);

    $attachment = $upload->handle(
        $invoice,
        $request->file('file'),
        $request->only(['caption', 'group']),
    );

    return AttachmentResource::make($attachment);
}
```

`handle()` only accepts descriptive attributes (`caption`, `group`,
`is_default`, `sort_order`), so request input can be passed straight through.
The disk is chosen by your code (the fourth argument), never by the request.

It throws `FileTooLargeException` or `DisallowedMimeException`, both
`AttachmentException`s, which you can turn into validation errors.

Read attachments through the relations:

```php
$invoice->attachments;                 // ordered by sort_order
$invoice->attachmentsInGroup('receipts')->get();
$invoice->defaultAttachment;
$invoice->firstAttachment;             // the default, else the first
```

`Kongpda\LaravelAttachments\Http\Resources\AttachmentResource` is the JSON
shape both UI packages consume: `id`, `file_name`, `file_size`, `file_type`,
`caption`, `group`, `is_default`, `url`, `thumbnail_url`, `is_previewable`,
`is_image` and `created_at`.

## Serving files

`url` and `thumbnail_url` point at two package routes:

| Route | Name |
| --- | --- |
| `GET /attachments/{attachment}/download` | `attachment.download` |
| `GET /attachments/{attachment}/thumbnail` | `attachment.thumbnail` |

Both require an authenticated user who can `view` the attachment's parent
model: write a `view` ability in the parent's policy. An attachment whose
parent is gone returns 404.

Responses carry `X-Content-Type-Options: nosniff`, `Cache-Control: private`
and an ETag. Only JPEG, PNG, GIF, WebP and PDF are served inline; every other
type is a download, so an uploaded HTML or SVG file never runs on your origin.
A matching `If-None-Match` returns 304 without touching storage.

To serve from a disk directly instead, list it under
`storage.temporary_url_disks` (short-lived signed URLs) or
`storage.direct_url_disks` (public URLs). Only list a disk in
`direct_url_disks` if it is served from a separate origin, such as a CDN
domain.

## Authorisation

`Kongpda\LaravelAttachments\Policies\AttachmentPolicy` delegates every ability
to the parent model: `view` needs `view` on the parent, and `update`,
`delete`, `restore` and `forceDelete` need `update` on it. `viewAny` and
`create` are always denied, so check the parent model instead. Register it in
your app:

```php
Gate::policy(Attachment::class, AttachmentPolicy::class);
```

To authorise some other way, bind your own
`Contracts\AttachmentAuthorizer` in `services.authorizer`.

## Deleting

Attachments soft-delete, and their files stay in place so a restore still
works. `attachments:prune` force-deletes attachments that have been in the
trash longer than `prune.after_days` (30 by default), and deletes their files
first. A row whose files cannot be deleted is kept, so it can be retried.

```bash
php artisan attachments:prune --days=7
```

## Configuration

| Key | Default | Purpose |
| --- | --- | --- |
| `route_middleware` | `['web', 'auth', 'throttle:300,1']` | Middleware on the serve routes. Keep `web`: it provides the session and route model binding. |
| `route_prefix` | `''` | Prefix for the serve routes. |
| `load_routes` / `load_migrations` | `true` | Disable them to register your own. |
| `storage.default_disk` | `ATTACHMENTS_DISK`, else `FILESYSTEM_DISK` | Disk for new uploads. |
| `storage.path_prefixes` | `[]` | Root directory per morph class, for example `['invoice' => 'finance']`. Classes not listed use `attachments`. |
| `uploads.max_upload_size_kb` | `10240` | Largest accepted upload. |
| `uploads.allowed_mimes` | images, PDF, text, Office, zip | Matched against sniffed content. An empty array allows anything, so use it only when every uploader is trusted. |
| `thumbnails.queued` | `true` | Generate thumbnails on the queue. |
| `thumbnails.max_source_pixels` | `40000000` | Images declaring more pixels get no thumbnail, which guards against decompression bombs. |
| `prune.after_days` | `30` | See [Deleting](#deleting). |
| `services.*` | package defaults | Swap the path generator, storage or authorizer. |

## Security

See [SECURITY.md](SECURITY.md) to report a vulnerability. The notes above
explain the defaults that keep uploads safe. The two most important:

- Keep uploads behind the download route.
- Keep `allowed_mimes` restricted.

## Testing

```bash
composer test
composer analyse
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Credits

- [kongpda](https://github.com/kongpda)
- Scaffolded from [spatie/package-skeleton-laravel](https://github.com/spatie/package-skeleton-laravel), and built on [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools).

## License

MIT. See [LICENSE.md](LICENSE.md).
