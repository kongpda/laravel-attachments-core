<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(AttachmentConfig::attachmentModel(), 'attachable')
            ->orderBy('sort_order');
    }

    public function firstAttachment(): MorphOne
    {
        return $this->morphOne(AttachmentConfig::attachmentModel(), 'attachable')
            ->orderByDesc('is_default')
            ->orderBy('sort_order');
    }

    public function defaultAttachment(): MorphOne
    {
        return $this->morphOne(AttachmentConfig::attachmentModel(), 'attachable')
            ->where('is_default', true);
    }

    public function attachmentsInGroup(string $group): MorphMany
    {
        return $this->attachments()->where('group', $group);
    }

    public function createAttachment(array $attributes): object
    {
        $isFirst = ! array_key_exists('is_default', $attributes)
            && ! $this->attachments()->exists();

        return $this->attachments()->create(array_merge($attributes, [
            'is_default' => $attributes['is_default'] ?? $isFirst,
            'disk' => $attributes['disk'] ?? AttachmentConfig::defaultDisk(),
        ]));
    }
}
