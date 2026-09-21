<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Actions\UploadAttachment;
use Kongpda\LaravelAttachments\Jobs\GenerateAttachmentThumbnailJob;
use Kongpda\LaravelAttachments\Tests\Fixtures\Post;

beforeEach(function (): void {
    Post::migrate();
    Storage::fake('local');
    Queue::fake();
    config()->set('attachments.storage.default_disk', 'local');
    config()->set('attachments.thumbnails.queued', true);
});

it('writes a 200px jpeg beside the file and records where it is', function (): void {
    $attachment = app(UploadAttachment::class)->handle(Post::create(), UploadedFile::fake()->image('photo.png', 640, 480));

    (new GenerateAttachmentThumbnailJob($attachment->id))->handle();

    $thumbnailPath = $attachment->fresh()->thumbnail_path;

    expect($thumbnailPath)->toStartWith(dirname($attachment->file_path).'/thumbnail/');

    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($thumbnailPath));

    expect([$width, $height])->toBe([200, 200]);
});

it('skips an image declaring more pixels than the limit instead of decoding it', function (): void {
    config()->set('attachments.thumbnails.max_source_pixels', 100 * 100);

    $attachment = app(UploadAttachment::class)->handle(Post::create(), UploadedFile::fake()->image('photo.png', 640, 480));

    (new GenerateAttachmentThumbnailJob($attachment->id))->handle();

    expect($attachment->fresh()->thumbnail_path)->toBeNull();
});

it('does nothing when the file or the record has gone', function (): void {
    $attachment = app(UploadAttachment::class)->handle(Post::create(), UploadedFile::fake()->image('photo.png', 64, 64));
    Storage::disk('local')->delete($attachment->file_path);

    (new GenerateAttachmentThumbnailJob($attachment->id))->handle();
    (new GenerateAttachmentThumbnailJob('01J00000000000000000000000'))->handle();

    expect($attachment->fresh()->thumbnail_path)->toBeNull();
});
