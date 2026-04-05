<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Models\Attachment;

final class GateAttachmentAuthorizer implements AttachmentAuthorizer
{
    public function authorizeViewAttachment(Attachment $attachment): void
    {
        $attachable = $attachment->attachable;

        abort_unless($attachable, 404, 'Attachment parent not found.');

        Gate::authorize('view', $attachable);
    }

    public function authorizeManageAttachable(Model $attachable, string $ability = 'update'): void
    {
        Gate::authorize($ability, $attachable);
    }
}
