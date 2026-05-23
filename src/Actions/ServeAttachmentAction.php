<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Actions;

use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;
use Kongpda\LaravelAttachments\Support\PreviewableMimes;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServeAttachmentAction
{
    public function __construct(private readonly AttachmentAuthorizer $authorizer) {}

    public function download(Attachment $attachment): StreamedResponse
    {
        return $this->serve($attachment, $attachment->file_path, $attachment->file_name);
    }

    public function thumbnail(Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->thumbnail_path !== null, 404);

        return $this->serve($attachment, $attachment->thumbnail_path, null);
    }

    private function serve(Attachment $attachment, string $path, ?string $downloadName): StreamedResponse
    {
        $this->authorizer->authorizeViewAttachment($attachment);

        $disk = Storage::disk($attachment->disk ?? AttachmentConfig::defaultDisk());
        abort_unless($disk->exists($path), 404, 'File not found in storage.');

        $mime = $disk->mimeType($path) ?: $attachment->file_type;
        $size = $disk->size($path);
        $lastModified = $disk->lastModified($path);
        $filename = $downloadName ?? basename($path);

        $disposition = PreviewableMimes::isPreviewable($mime)
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return $disk->response($path, $filename, [
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Cache-Control' => 'private, max-age=3600',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}
