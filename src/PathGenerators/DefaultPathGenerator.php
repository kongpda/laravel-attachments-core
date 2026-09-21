<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\PathGenerators;

use Illuminate\Database\Eloquent\Model;
use Kongpda\LaravelAttachments\Contracts\PathGenerator;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;
use Kongpda\LaravelAttachments\Support\FilesystemAttachmentStorage;

final class DefaultPathGenerator implements PathGenerator
{
    public function pathsForUpload(Model $attachable, string $attachmentId, string $originalName): array
    {
        $directory = mb_ltrim(sprintf(
            '%s/%s/%s',
            mb_trim(AttachmentConfig::storagePathPrefixFor($attachable->getMorphClass()), '/'),
            $attachable->getMorphClass(),
            $attachmentId,
        ), '/');
        $fileName = FilesystemAttachmentStorage::generateUniqueFilename($originalName);
        $filePath = sprintf('%s/%s', $directory, $fileName);
        $thumbnailPath = sprintf(
            '%s/%s/%s_thumbnail.jpg',
            $directory,
            FilesystemAttachmentStorage::THUMBNAIL_SUBFOLDER,
            pathinfo($fileName, PATHINFO_FILENAME),
        );

        return [
            'file_path' => $filePath,
            'thumbnail_path' => $thumbnailPath,
        ];
    }
}
