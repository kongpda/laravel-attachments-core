<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kongpda\LaravelAttachments\Models\Attachment;

/**
 * @mixin Attachment
 */
final class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'caption' => $this->caption,
            'group' => $this->group,
            'is_default' => $this->is_default,
            'url' => $this->getUrl(),
            'thumbnail_url' => $this->getThumbnailUrl(),
            'is_previewable' => $this->isPreviewable(),
            'is_image' => $this->hasImage(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
