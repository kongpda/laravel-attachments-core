<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\StoredAttachment;
use Kongpda\LaravelAttachments\Exceptions\AttachmentException;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;
use Throwable;

final class FilesystemAttachmentStorage implements AttachmentStorage
{
    public const string THUMBNAIL_SUBFOLDER = 'thumbnail';

    public static function generateUniqueFilename(string $originalName, int $hashLength = 5): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug($nameWithoutExtension);

        if ($slug === '') {
            $slug = 'file';
        }

        $slug = Str::limit($slug, 50, '');
        $hash = Str::random($hashLength);

        return $extension !== '' && $extension !== '0'
            ? sprintf('%s-%s.%s', $slug, $hash, $extension)
            : sprintf('%s-%s', $slug, $hash);
    }

    public function storeUploadedFile(
        UploadedFile $file,
        string $filePath,
        ?string $thumbnailPath = null,
        ?string $disk = null,
    ): array {
        $disk ??= AttachmentConfig::defaultDisk();
        $stored = Storage::disk($disk)->putFileAs(dirname($filePath), $file, basename($filePath));

        if (! is_string($stored) || $stored === '') {
            throw new AttachmentException('Unable to store uploaded attachment file.');
        }

        $storedThumbnail = null;

        if ($thumbnailPath && $this->isImageFile($file)) {
            $storedThumbnail = $this->generateImageThumbnailFromPath(
                $file->getRealPath() ?: $file->path(),
                dirname($thumbnailPath),
                $disk,
                pathinfo($filePath, PATHINFO_FILENAME),
            );
        } elseif ($thumbnailPath
            && $this->isPdfFile($file)
            && AttachmentConfig::pdfThumbnailsEnabled()) {
            $storedThumbnail = $this->generatePdfThumbnailFromPath(
                $file->getRealPath() ?: $file->path(),
                dirname($thumbnailPath),
                $disk,
                pathinfo($filePath, PATHINFO_FILENAME),
            );
        }

        return [
            'path' => $stored,
            'thumbnail_path' => $storedThumbnail,
        ];
    }

    public function deleteAttachmentFiles(StoredAttachment $attachment): bool
    {
        $deleted = true;
        $disk = Storage::disk($attachment->disk ?? AttachmentConfig::defaultDisk());

        if ($attachment->file_path && $disk->exists($attachment->file_path)) {
            $deleted = $disk->delete($attachment->file_path);
        }

        if ($attachment->thumbnail_path && $disk->exists($attachment->thumbnail_path)) {
            $deleted = $disk->delete($attachment->thumbnail_path) && $deleted;
        }

        return $deleted;
    }

    public function isPreviewableMime(?string $mime): bool
    {
        return PreviewableMimes::isPreviewable($mime);
    }

    public function generateImageThumbnailFromPath(
        string $sourcePath,
        string $directory,
        string $disk,
        string $mainBasename,
    ): ?string {
        if ($sourcePath === '' || ! is_readable($sourcePath)) {
            return null;
        }

        $thumbnailPath = sprintf('%s/%s_thumbnail.jpg', $directory, $mainBasename);

        // Read the declared size from the header first: decoding is what
        // allocates, and a few kilobytes can declare a gigapixel canvas.
        $size = @getimagesize($sourcePath);

        if ($size === false || AttachmentConfig::maxSourcePixels() < $size[0] * $size[1]) {
            return null;
        }

        try {
            $image = $this->decodeImage($sourcePath);
            $image->scaleDown(AttachmentConfig::maxImageDimension(), AttachmentConfig::maxImageDimension());
            $image->coverDown(200, 200);
            $encoded = $image->encode(new JpegEncoder(quality: 80));

            $written = Storage::disk($disk)->put($thumbnailPath, (string) $encoded);

            return $written ? $thumbnailPath : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function generatePdfThumbnailFromPath(
        string $sourcePath,
        string $directory,
        string $disk,
        string $mainBasename,
    ): ?string {
        if ($sourcePath === ''
            || ! is_readable($sourcePath)
            || ! AttachmentConfig::pdfThumbnailsEnabled()
            || ! class_exists(Pdf::class)) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'att_pdf_thumb_');

        if ($tempPath === false) {
            return null;
        }

        try {
            $pdf = new Pdf($sourcePath);
            $pdf->format(OutputFormat::Jpeg)
                ->selectPage(1)
                ->thumbnailSize(400)
                ->save($tempPath);

            $contents = file_get_contents($tempPath);

            if (! is_string($contents) || $contents === '') {
                return null;
            }

            $thumbnailPath = sprintf('%s/%s_thumbnail.jpg', $directory, $mainBasename);
            $written = Storage::disk($disk)->put($thumbnailPath, $contents);

            return $written ? $thumbnailPath : null;
        } catch (Throwable) {
            return null;
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Intervention is used directly rather than through its Laravel bridge:
     * Laravel 13 binds its own image manager to the same container key, and
     * the two major versions a host may have installed name this call differently.
     */
    private function decodeImage(string $sourcePath): ImageInterface
    {
        if (method_exists(ImageManager::class, 'usingDriver')) {
            return ImageManager::usingDriver(GdDriver::class)->decodePath($sourcePath);
        }

        return ImageManager::gd()->read($sourcePath);
    }

    private function isImageFile(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ], true);
    }

    private function isPdfFile(UploadedFile $file): bool
    {
        return $file->getMimeType() === 'application/pdf';
    }
}
