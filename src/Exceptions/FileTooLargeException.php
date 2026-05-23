<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Exceptions;

final class FileTooLargeException extends AttachmentException
{
    public static function forSize(int $bytes, int $maxKb): self
    {
        return new self(sprintf(
            'Uploaded file is %d KB which exceeds the maximum allowed size of %d KB.',
            (int) ceil($bytes / 1024),
            $maxKb,
        ));
    }
}
