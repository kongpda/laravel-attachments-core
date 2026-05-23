<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Exceptions;

final class DisallowedMimeException extends AttachmentException
{
    public static function forMime(?string $mime): self
    {
        return new self(sprintf(
            'Uploaded file type "%s" is not allowed.',
            $mime ?? 'unknown',
        ));
    }
}
