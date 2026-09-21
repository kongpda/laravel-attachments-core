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
use Illuminate\Support\Carbon;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\StoredAttachment;
use Kongpda\LaravelAttachments\Models\Concerns\HasStoredAttachmentUrls;
use Kongpda\LaravelAttachments\Observers\AttachmentObserver;
use Kongpda\LaravelAttachments\Policies\AttachmentPolicy;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;

/**
 * @property string $id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property string $file_name
 * @property string $file_path
 * @property string|null $thumbnail_path
 * @property string $file_type
 * @property int $file_size
 * @property string $disk
 * @property string|null $caption
 * @property string|null $group
 * @property bool $is_default
 * @property int $sort_order
 * @property string|null $uploaded_by
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[ObservedBy(AttachmentObserver::class)]
#[UsePolicy(AttachmentPolicy::class)]
class Attachment extends Model implements StoredAttachment
{
    use HasFactory;
    use HasStoredAttachmentUrls;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'attachments';

    /**
     * Descriptive columns only. Where the file lives, what it is attached to
     * and who uploaded it are set by the package, so request input can never
     * repoint an attachment at another file or another owner.
     */
    protected $fillable = [
        'file_name',
        'caption',
        'group',
        'is_default',
        'sort_order',
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
        return $query->where(function ($query): void {
            $query->where('group', '!=', 'image')
                ->orWhereNull('group');
        });
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
