<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Contracts;

use Illuminate\Database\Eloquent\Model;
use Kongpda\LaravelAttachments\Models\Attachment;

interface AttachmentAuthorizer
{
    public function authorizeViewAttachment(Attachment $attachment): void;

    public function authorizeManageAttachable(Model $attachable, string $ability = 'update'): void;
}
