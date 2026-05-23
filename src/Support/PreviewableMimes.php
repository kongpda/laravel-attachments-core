<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Support;

/**
 * Canonical list of MIME types that are safe to preview/render inline in a browser.
 * Single source of truth shared by storage, the serve action, and UI helpers.
 */
final class PreviewableMimes
{
    /**
     * @var array<int, string>
     */
    public const array LIST = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    public static function isPreviewable(?string $mime): bool
    {
        if ($mime === null || $mime === '') {
            return false;
        }

        return in_array($mime, self::LIST, true);
    }
}
