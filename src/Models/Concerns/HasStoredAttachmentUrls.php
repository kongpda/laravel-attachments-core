<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\StoredAttachment;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;

/**
 * @mixin Model
 * @mixin StoredAttachment
 */
trait HasStoredAttachmentUrls
{
    public function getUrl(): string
    {
        return $this->urlFor($this->file_path, 'attachment.download');
    }

    public function getThumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return $this->urlFor($this->thumbnail_path, 'attachment.thumbnail');
    }

    public function hasImage(): bool
    {
        return filled($this->file_type) && str_starts_with((string) $this->file_type, 'image/');
    }

    public function isPreviewable(): bool
    {
        return app(AttachmentStorage::class)->isPreviewableMime($this->file_type);
    }

    public function deleteFromStorage(): bool
    {
        $deleted = app(AttachmentStorage::class)->deleteAttachmentFiles($this);

        if ($deleted) {
            $this->delete();
        }

        return $deleted;
    }

    /**
     * The authorised proxy route unless the disk has been explicitly opted in
     * to signed or direct URLs. A raw storage URL on the app's own origin would
     * serve an uploaded HTML or SVG file as the app.
     */
    private function urlFor(string $path, string $proxyRoute): string
    {
        $disk = $this->disk ?? AttachmentConfig::defaultDisk();

        if (in_array($disk, AttachmentConfig::temporaryUrlDisks(), true)) {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(10));
        }

        if (in_array($disk, AttachmentConfig::directUrlDisks(), true)) {
            return Storage::disk($disk)->url($path);
        }

        return route($proxyRoute, $this);
    }
}
