<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\PathGenerator;
use Kongpda\LaravelAttachments\Events\AttachmentUploaded;
use Kongpda\LaravelAttachments\Exceptions\AttachmentException;
use Kongpda\LaravelAttachments\Exceptions\DisallowedMimeException;
use Kongpda\LaravelAttachments\Exceptions\FileTooLargeException;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Models\Concerns\HasAttachments;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;

/**
 * Turnkey upload pipeline: validate -> generate paths -> store file -> persist record.
 *
 * The attachable model must use the {@see HasAttachments} trait.
 */
final class UploadAttachment
{
    private const array DESCRIPTIVE_ATTRIBUTES = ['caption', 'group', 'is_default', 'sort_order'];

    public function __construct(
        private readonly PathGenerator $pathGenerator,
        private readonly AttachmentStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Descriptive columns only (caption, group, is_default, sort_order). Anything else is dropped, so request input can be passed straight through.
     * @param  string|null  $disk  Chosen by the calling code, never by request input.
     */
    public function handle(Model $attachable, UploadedFile $file, array $attributes = [], ?string $disk = null): Attachment
    {
        $this->guardSize($file);
        $mime = $this->guardMime($file);

        $attributes = array_intersect_key($attributes, array_flip(self::DESCRIPTIVE_ATTRIBUTES));
        $disk ??= AttachmentConfig::defaultDisk();
        $id = (string) Str::ulid();

        $paths = $this->pathGenerator->pathsForUpload($attachable, $id, $file->getClientOriginalName());

        $stored = $this->storage->storeUploadedFile(
            $file,
            $paths['file_path'],
            AttachmentConfig::thumbnailsQueued() ? null : $paths['thumbnail_path'],
            $disk,
        );

        $storedPath = $stored['path'] ?? null;

        if (! is_string($storedPath) || $storedPath === '') {
            throw new AttachmentException('Unable to store uploaded attachment file.');
        }

        // The attachable must use the HasAttachments trait (documented requirement).
        /** @var Attachment $attachment */
        $attachment = $attachable->createAttachment(array_merge($attributes, [
            'id' => $id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'thumbnail_path' => $stored['thumbnail_path'] ?? null,
            'file_type' => $mime,
            'file_size' => (int) $file->getSize(),
            'disk' => $disk,
            'uploaded_by' => Auth::id(),
        ]));

        AttachmentUploaded::dispatch($attachment);

        return $attachment;
    }

    private function guardSize(UploadedFile $file): void
    {
        $maxKb = AttachmentConfig::maxUploadSizeKb();
        $size = (int) $file->getSize();

        if ($maxKb > 0 && $size > $maxKb * 1024) {
            throw FileTooLargeException::forSize($size, $maxKb);
        }
    }

    private function guardMime(UploadedFile $file): string
    {
        // Sniffed from the contents. The client's own claim is never a fallback:
        // it is the one value an attacker fully controls.
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $allowed = AttachmentConfig::allowedMimes();

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw DisallowedMimeException::forMime($mime);
        }

        return $mime;
    }
}
