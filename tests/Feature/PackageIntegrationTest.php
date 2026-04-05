<?php

declare(strict_types=1);

use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\PathGenerator;
use Kongpda\LaravelAttachments\PathGenerators\DefaultPathGenerator;
use Kongpda\LaravelAttachments\Support\FilesystemAttachmentStorage;
use Kongpda\LaravelAttachments\Support\GateAttachmentAuthorizer;

it('resolves default service bindings when services config is omitted', function (): void {
    config()->set('attachments.services', null);

    expect(app(PathGenerator::class))->toBeInstanceOf(DefaultPathGenerator::class)
        ->and(app(AttachmentStorage::class))->toBeInstanceOf(FilesystemAttachmentStorage::class)
        ->and(app(AttachmentAuthorizer::class))->toBeInstanceOf(GateAttachmentAuthorizer::class);
});

it('does not register package views', function (): void {
    expect(view()->exists('laravel-attachments::livewire.attachment-section'))->toBeFalse();
});
