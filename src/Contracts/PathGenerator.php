<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PathGenerator
{
    /**
     * @return array{file_path: string, thumbnail_path: string}
     */
    public function pathsForUpload(Model $attachable, string $attachmentId, string $originalName): array;
}
