<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;
use Kongpda\LaravelAttachments\Support\PreviewableMimes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ServeAttachmentAction
{
    public function __construct(private readonly AttachmentAuthorizer $authorizer) {}

    public function download(Request $request, Attachment $attachment): Response
    {
        return $this->serve(
            $request,
            $attachment,
            $attachment->file_path,
            $attachment->file_name,
            $attachment->file_type,
            ['Content-Length' => $attachment->file_size],
        );
    }

    public function thumbnail(Request $request, Attachment $attachment): Response
    {
        abort_unless($attachment->thumbnail_path !== null, 404);

        return $this->serve($request, $attachment, $attachment->thumbnail_path, basename($attachment->thumbnail_path), 'image/jpeg');
    }

    /**
     * The type comes from the record, where the upload sniffed it, and a stored
     * file never changes, so its path is its validator. A browser that already
     * holds the file is answered before storage is touched at all, which on a
     * remote disk is the difference between no round trips and three.
     *
     * @param  array<string, int|string>  $headers
     */
    private function serve(Request $request, Attachment $attachment, string $path, string $filename, string $mime, array $headers = []): Response
    {
        $this->authorizer->authorizeViewAttachment($attachment);

        $notModified = (new Response)->setEtag(hash('xxh128', $attachment->id.'|'.$path));
        $notModified->headers->set('Cache-Control', 'private, max-age=3600');

        if ($notModified->isNotModified($request)) {
            return $notModified;
        }

        $disk = Storage::disk($attachment->disk ?? AttachmentConfig::defaultDisk());
        abort_unless($disk->exists($path), 404, 'File not found in storage.');

        $disposition = PreviewableMimes::isPreviewable($mime)
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return $disk->response($path, $filename, [
            ...$headers,
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'ETag' => $notModified->getEtag(),
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}
