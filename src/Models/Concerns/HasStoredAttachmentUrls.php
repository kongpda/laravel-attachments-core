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
        if ($this->shouldUseProxyRoute($this->disk ?? AttachmentConfig::defaultDisk())) {
            return route('attachment.download', $this);
        }

        if ($this->supportsTemporaryUrls($this->disk ?? AttachmentConfig::defaultDisk())) {
            return Storage::disk($this->disk)->temporaryUrl($this->file_path, now()->addMinutes(10));
        }

        return Storage::disk($this->disk ?? AttachmentConfig::defaultDisk())->url($this->file_path);
    }

    public function getThumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        if ($this->shouldUseProxyRoute($this->disk ?? AttachmentConfig::defaultDisk())) {
            return route('attachment.thumbnail', $this);
        }

        if ($this->supportsTemporaryUrls($this->disk ?? AttachmentConfig::defaultDisk())) {
            return Storage::disk($this->disk)->temporaryUrl($this->thumbnail_path, now()->addMinutes(10));
        }

        return Storage::disk($this->disk ?? AttachmentConfig::defaultDisk())->url($this->thumbnail_path);
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

    private function shouldUseProxyRoute(string $disk): bool
    {
        return in_array($disk, AttachmentConfig::proxyDownloadDisks(), true);
    }

    private function supportsTemporaryUrls(string $disk): bool
    {
        return in_array($disk, AttachmentConfig::temporaryUrlDisks(), true);
    }
}
