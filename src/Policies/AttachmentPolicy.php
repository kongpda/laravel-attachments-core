<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Policies;

use Illuminate\Foundation\Auth\User;
use Kongpda\LaravelAttachments\Models\Attachment;

class AttachmentPolicy
{
    public function viewAny(): bool
    {
        return false;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        return $attachable !== null && $user->can('view', $attachable);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        return $attachable !== null && $user->can('update', $attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->update($user, $attachment);
    }

    public function restore(User $user, Attachment $attachment): bool
    {
        return $this->update($user, $attachment);
    }

    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $this->update($user, $attachment);
    }
}
