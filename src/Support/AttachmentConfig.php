<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Support;

use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\PathGenerators\DefaultPathGenerator;

final class AttachmentConfig
{
    public static function attachmentModel(): string
    {
        return (string) config('attachments.models.attachment', Attachment::class);
    }

    public static function defaultDisk(): string
    {
        return (string) config(
            'attachments.storage.default_disk',
            config('filesystems.default', 'local'),
        );
    }

    public static function directUrlDisks(): array
    {
        return array_values(config('attachments.storage.direct_url_disks', []));
    }

    public static function temporaryUrlDisks(): array
    {
        return array_values(config('attachments.storage.temporary_url_disks', []));
    }

    public static function storagePathPrefixFor(string $attachableType): string
    {
        $prefixes = config('attachments.storage.path_prefixes', []);

        if (! is_array($prefixes)) {
            return 'attachments';
        }

        return (string) ($prefixes[$attachableType] ?? 'attachments');
    }

    public static function pathGeneratorClass(): string
    {
        return (string) config('attachments.services.path_generator', DefaultPathGenerator::class);
    }

    public static function storageClass(): string
    {
        return (string) config('attachments.services.storage', FilesystemAttachmentStorage::class);
    }

    public static function authorizerClass(): string
    {
        return (string) config('attachments.services.authorizer', GateAttachmentAuthorizer::class);
    }

    public static function thumbnailsQueued(): bool
    {
        return (bool) config('attachments.thumbnails.queued', true);
    }

    public static function pdfThumbnailsEnabled(): bool
    {
        return (bool) config('attachments.thumbnails.pdf_enabled', false);
    }

    public static function maxImageDimension(): int
    {
        return (int) config('attachments.thumbnails.max_image_dimension', 4000);
    }

    public static function maxSourcePixels(): int
    {
        return (int) config('attachments.thumbnails.max_source_pixels', 40_000_000);
    }

    public static function pruneAfterDays(): int
    {
        return (int) config('attachments.prune.after_days', 30);
    }

    public static function maxUploadSizeKb(): int
    {
        return (int) config('attachments.uploads.max_upload_size_kb', 10240);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedMimes(): array
    {
        $mimes = config('attachments.uploads.allowed_mimes', []);

        return is_array($mimes) ? array_values($mimes) : [];
    }

    public static function loadRoutes(): bool
    {
        return (bool) config('attachments.load_routes', true);
    }

    public static function uiDriver(): string
    {
        return (string) config('attachments.ui.driver', 'default');
    }

    public static function usesFluxUi(): bool
    {
        return self::uiDriver() === 'flux';
    }

    public static function loadMigrations(): bool
    {
        return (bool) config('attachments.load_migrations', true);
    }

    public static function routeMiddleware(): array
    {
        return array_values(config('attachments.route_middleware', ['web', 'auth']));
    }

    public static function routePrefix(): string
    {
        return mb_trim((string) config('attachments.route_prefix', ''), '/');
    }
}
