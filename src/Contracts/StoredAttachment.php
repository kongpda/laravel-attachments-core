<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Contracts;

/**
 * @property string $file_path
 * @property string|null $thumbnail_path
 * @property string $file_type
 * @property string $disk
 */
interface StoredAttachment
{
    public function getUrl(): string;

    public function getThumbnailUrl(): ?string;

    public function deleteFromStorage(): bool;

    public function hasImage(): bool;

    public function isPreviewable(): bool;

    public function getStoragePathPrefix(): string;
}
