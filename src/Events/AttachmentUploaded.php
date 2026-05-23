<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kongpda\LaravelAttachments\Models\Attachment;

final class AttachmentUploaded
{
    use Dispatchable;

    public function __construct(public readonly Attachment $attachment) {}
}
