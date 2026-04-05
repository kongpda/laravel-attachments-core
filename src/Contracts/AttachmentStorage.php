<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Contracts;

use Illuminate\Http\UploadedFile;

interface AttachmentStorage
{
    public function storeUploadedFile(
        UploadedFile $file,
        string $filePath,
        ?string $thumbnailPath = null,
        ?string $disk = null,
    ): array;

    public function deleteAttachmentFiles(StoredAttachment $attachment): bool;

    public function isPreviewableMime(?string $mime): bool;

    public function generateImageThumbnailFromPath(
        string $sourcePath,
        string $directory,
        string $disk,
        string $mainBasename,
    ): ?string;

    public function generatePdfThumbnailFromPath(
        string $sourcePath,
        string $directory,
        string $disk,
        string $mainBasename,
    ): ?string;
}
