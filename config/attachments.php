<?php

declare(strict_types=1);

use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\PathGenerators\DefaultPathGenerator;
use Kongpda\LaravelAttachments\Support\FilesystemAttachmentStorage;
use Kongpda\LaravelAttachments\Support\GateAttachmentAuthorizer;

return [
    'load_routes' => true,
    'load_migrations' => true,
    // "web" is what gives these routes a session and route model binding:
    // without it nobody is ever signed in and no attachment is ever resolved.
    'route_middleware' => ['web', 'auth', 'throttle:300,1'],
    'route_prefix' => '',
    'models' => [
        'attachment' => Attachment::class,
    ],
    'storage' => [
        'default_disk' => env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        // Files are served through the package's authorised download route by
        // default, which sends nosniff and a safe Content-Disposition. List a
        // disk here to hand out short-lived signed URLs instead.
        'temporary_url_disks' => [],
        // Disks whose raw Storage::url() may be exposed. Only list a disk served
        // from a separate origin (a CDN domain): on the app's own origin an
        // uploaded HTML or SVG file would run as the app.
        'direct_url_disks' => [],
        // Root directory per morph class, e.g. ['invoice' => 'finance']. Types
        // not listed are stored under "attachments". Applies to new uploads.
        'path_prefixes' => [],
    ],
    // Soft-deleted attachments keep their files so a restore still works.
    // `attachments:prune` force-deletes the ones trashed longer than this, files
    // included. Schedule it daily.
    'prune' => [
        'after_days' => env('ATTACHMENTS_PRUNE_AFTER_DAYS', 30),
    ],
    'thumbnails' => [
        'queued' => env('ATTACHMENTS_THUMBNAILS_QUEUED', true),
        'pdf_enabled' => env('ATTACHMENTS_PDF_THUMBNAILS_ENABLED', false),
        'max_image_dimension' => env('ATTACHMENTS_MAX_IMAGE_DIMENSION', 4000),
        // Images declaring more pixels than this get no thumbnail: decoding is
        // what costs memory, and a tiny file can declare enormous dimensions.
        'max_source_pixels' => env('ATTACHMENTS_MAX_SOURCE_PIXELS', 40_000_000),
    ],
    'uploads' => [
        'max_upload_size_kb' => env('ATTACHMENTS_MAX_UPLOAD_SIZE_KB', 10240),
        // Allowed MIME types for UploadAttachment, matched against the sniffed
        // file contents. An empty array allows any type; only do that when
        // every uploader is trusted.
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/zip',
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
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
