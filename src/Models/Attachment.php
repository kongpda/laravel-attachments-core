<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\StoredAttachment;
use Kongpda\LaravelAttachments\Models\Concerns\HasStoredAttachmentUrls;
use Kongpda\LaravelAttachments\Observers\AttachmentObserver;
use Kongpda\LaravelAttachments\Policies\AttachmentPolicy;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;

#[ObservedBy(AttachmentObserver::class)]
#[UsePolicy(AttachmentPolicy::class)]
class Attachment extends Model implements StoredAttachment
{
    use HasFactory;
    use HasStoredAttachmentUrls;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'attachments';

    protected $fillable = [
        'id',
        'attachable_type',
        'attachable_id',
        'file_name',
        'file_path',
        'thumbnail_path',
        'file_type',
        'file_size',
        'disk',
        'caption',
        'group',
        'is_default',
        'sort_order',
        'uploaded_by',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'), 'uploaded_by');
    }

    public function getStoragePathPrefix(): string
    {
        return AttachmentConfig::storagePathPrefixFor((string) $this->attachable_type);
    }

    public function getFormattedSize(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public function forceDeleteFromStorage(): bool
    {
        app(AttachmentStorage::class)->deleteAttachmentFiles($this);

        return $this->forceDelete();
    }

    public function getDownloadUrl(): string
    {
        return route('attachment.download', $this);
    }

    public function getThumbnailRoute(): ?string
    {
        return $this->thumbnail_path
            ? route('attachment.thumbnail', $this)
            : null;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->attributes['caption'] ?? null;
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['caption'] = $value;
    }

    #[Scope]
    protected function images($query)
    {
        return $query->where('group', 'image');
    }

    #[Scope]
    protected function documents($query)
    {
        return $query->where('group', '!=', 'image')
            ->orWhereNull('group');
    }

    #[Scope]
    protected function inGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
