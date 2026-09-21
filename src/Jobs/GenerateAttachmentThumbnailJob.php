<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;
use Kongpda\LaravelAttachments\Support\FilesystemAttachmentStorage;

class GenerateAttachmentThumbnailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $attachmentId) {}

    public function handle(?AttachmentStorage $storage = null): void
    {
        $storage ??= app(AttachmentStorage::class);

        /** @var Attachment|null $attachment */
        $attachment = Attachment::find($this->attachmentId);

        if (! $attachment || ! $attachment->file_path || $attachment->thumbnail_path) {
            return;
        }

        $disk = Storage::disk($attachment->disk ?? AttachmentConfig::defaultDisk());

        if (! $disk->exists($attachment->file_path)) {
            Log::warning('Attachment file not found for thumbnail: '.$attachment->file_path);

            return;
        }

        $extension = pathinfo((string) $attachment->file_path, PATHINFO_EXTENSION) ?: 'bin';
        $tempBasePath = tempnam(sys_get_temp_dir(), 'att_thumb_');

        if ($tempBasePath === false) {
            Log::warning('Unable to create temporary file for attachment thumbnail: '.$attachment->file_path);

            return;
        }

        $tempPath = $tempBasePath.'.'.$extension;

        try {
            if (file_put_contents($tempPath, $disk->get($attachment->file_path)) === false) {
                return;
            }

            $directory = dirname((string) $attachment->file_path).'/'.FilesystemAttachmentStorage::THUMBNAIL_SUBFOLDER;
            $mainBasename = pathinfo((string) $attachment->file_path, PATHINFO_FILENAME);
            $thumbnailPath = null;

            if ($attachment->hasImage()) {
                $thumbnailPath = $storage->generateImageThumbnailFromPath(
                    $tempPath,
                    $directory,
                    $attachment->disk ?? AttachmentConfig::defaultDisk(),
                    $mainBasename,
                );
            } elseif ($attachment->file_type === 'application/pdf' && AttachmentConfig::pdfThumbnailsEnabled()) {
                $thumbnailPath = $storage->generatePdfThumbnailFromPath(
                    $tempPath,
                    $directory,
                    $attachment->disk ?? AttachmentConfig::defaultDisk(),
                    $mainBasename,
                );
            }

            if ($thumbnailPath) {
                // Not fillable: where a file lives is never request input.
                $attachment->forceFill(['thumbnail_path' => $thumbnailPath])->save();
            }
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            if (file_exists($tempBasePath)) {
                @unlink($tempBasePath);
            }
        }
    }
}
