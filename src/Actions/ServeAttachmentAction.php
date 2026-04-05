<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Actions;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServeAttachmentAction
{
    public function __construct(private readonly AttachmentAuthorizer $authorizer) {}

    public function download(Attachment $attachment): Response|StreamedResponse
    {
        return $this->serve($attachment, $attachment->file_path, $attachment->file_name);
    }

    public function thumbnail(Attachment $attachment): Response|StreamedResponse
    {
        abort_unless($attachment->thumbnail_path, 404);

        return $this->serve($attachment, $attachment->thumbnail_path, null);
    }

    private function serve(Attachment $attachment, string $path, ?string $downloadName): Response|StreamedResponse
    {
        $this->authorizer->authorizeViewAttachment($attachment);

        $disk = Storage::disk($attachment->disk ?? AttachmentConfig::defaultDisk());
        abort_unless($disk->exists($path), 404, 'File not found in storage.');

        $mime = $disk->mimeType($path) ?: $attachment->file_type;
        $size = $disk->size($path);
        $lastModified = $disk->lastModified($path);
        $filename = $downloadName ?? basename($path);

        return $disk->response($path, $filename, [
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Cache-Control' => 'private, max-age=3600',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
        ]);
    }
}
