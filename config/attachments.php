<?php

declare(strict_types=1);

use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\PathGenerators\DefaultPathGenerator;
use Kongpda\LaravelAttachments\Support\FilesystemAttachmentStorage;
use Kongpda\LaravelAttachments\Support\GateAttachmentAuthorizer;

return [
    'load_routes' => true,
    'load_migrations' => true,
    'route_middleware' => ['auth'],
    'route_prefix' => '',
    'models' => [
        'attachment' => Attachment::class,
    ],
    'storage' => [
        'default_disk' => env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'proxy_download_disks' => ['r2', 's3'],
        'temporary_url_disks' => ['s3'],
        'path_prefixes' => [],
    ],
    'thumbnails' => [
        'queued' => env('ATTACHMENTS_THUMBNAILS_QUEUED', false),
        'pdf_enabled' => env('ATTACHMENTS_PDF_THUMBNAILS_ENABLED', false),
        'max_image_dimension' => env('ATTACHMENTS_MAX_IMAGE_DIMENSION', 4000),
    ],
    'uploads' => [
        'max_upload_size_kb' => env('ATTACHMENTS_MAX_UPLOAD_SIZE_KB', 10240),
        // Allowed MIME types for UploadAttachment. Empty = allow any type.
        'allowed_mimes' => [],
    ],
    'services' => [
        'path_generator' => DefaultPathGenerator::class,
        'storage' => FilesystemAttachmentStorage::class,
        'authorizer' => GateAttachmentAuthorizer::class,
    ],
    'livewire' => [
        'register_components' => true,
    ],
    'ui' => [
        'driver' => env('ATTACHMENTS_UI_DRIVER', 'default'),
    ],
];
