<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Observers;

use Kongpda\LaravelAttachments\Events\AttachmentDeleted;
use Kongpda\LaravelAttachments\Jobs\GenerateAttachmentThumbnailJob;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;

class AttachmentObserver
{
    public function created(Attachment $attachment): void
    {
        if (! $attachment->thumbnail_path
            && $attachment->isPreviewable()
            && AttachmentConfig::thumbnailsQueued()) {
            GenerateAttachmentThumbnailJob::dispatch($attachment->id);
        }
    }

    public function deleted(Attachment $attachment): void
    {
        if ($attachment->isForceDeleting()) {
            return;
        }

        AttachmentDeleted::dispatch($attachment, false);
    }

    public function forceDeleted(Attachment $attachment): void
    {
        AttachmentDeleted::dispatch($attachment, true);
    }
}
