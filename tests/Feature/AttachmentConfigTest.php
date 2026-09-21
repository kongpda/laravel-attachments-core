<?php

declare(strict_types=1);

use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\PathGenerator;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\PathGenerators\DefaultPathGenerator;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;
use Kongpda\LaravelAttachments\Support\FilesystemAttachmentStorage;
use Kongpda\LaravelAttachments\Support\GateAttachmentAuthorizer;

it('uses the package attachment model by default', function (): void {
    expect(AttachmentConfig::attachmentModel())->toBe(Attachment::class);
});

it('exposes no disk through signed or direct urls until the host opts one in', function (): void {
    expect(AttachmentConfig::temporaryUrlDisks())->toBe([])
        ->and(AttachmentConfig::directUrlDisks())->toBe([]);
});

it('keeps service bindings when the host app overrides unrelated config keys', function (): void {
    config()->set('attachments.ui.driver', 'flux');

    expect(app(PathGenerator::class))->toBeInstanceOf(DefaultPathGenerator::class)
        ->and(app(AttachmentStorage::class))->toBeInstanceOf(FilesystemAttachmentStorage::class)
        ->and(app(AttachmentAuthorizer::class))->toBeInstanceOf(GateAttachmentAuthorizer::class);
});

it('uses configured storage path prefixes', function (): void {
    config()->set('attachments.storage.path_prefixes', [
        'document' => 'dm-documents',
    ]);

    expect(AttachmentConfig::storagePathPrefixFor('document'))->toBe('dm-documents')
        ->and(AttachmentConfig::storagePathPrefixFor('employee'))->toBe('attachments')
        ->and(AttachmentConfig::storagePathPrefixFor(''))->toBe('attachments');
});
