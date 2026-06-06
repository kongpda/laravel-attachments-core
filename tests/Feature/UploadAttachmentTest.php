<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Actions\UploadAttachment;
use Kongpda\LaravelAttachments\Events\AttachmentUploaded;
use Kongpda\LaravelAttachments\Exceptions\DisallowedMimeException;
use Kongpda\LaravelAttachments\Exceptions\FileTooLargeException;
use Kongpda\LaravelAttachments\Jobs\GenerateAttachmentThumbnailJob;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Models\Concerns\HasAttachments;

/**
 * Minimal attachable fixture so the upload pipeline has a real morph parent.
 */
class UploadTestPost extends Model
{
    use HasAttachments;

    protected $table = 'upload_test_posts';

    protected $guarded = [];

    public function getMorphClass(): string
    {
        return 'upload-test-post';
    }
}

beforeEach(function (): void {
    Schema::create('upload_test_posts', function (Blueprint $table): void {
        $table->id();
        $table->timestamps();
    });
});

it('rejects files larger than the configured maximum before storing anything', function (): void {
    config()->set('attachments.uploads.max_upload_size_kb', 100);
    Storage::fake('local');

    $post = UploadTestPost::create();
    $file = UploadedFile::fake()->create('big.bin', 200); // 200 KB > 100 KB limit

    expect(fn () => app(UploadAttachment::class)->handle($post, $file))
        ->toThrow(FileTooLargeException::class);

    expect(Attachment::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('');
});

it('rejects mime types outside the allow list', function (): void {
    config()->set('attachments.uploads.allowed_mimes', ['application/pdf']);
    Storage::fake('local');

    $post = UploadTestPost::create();
    $file = UploadedFile::fake()->image('avatar.jpg'); // image/jpeg, not allowed

    expect(fn () => app(UploadAttachment::class)->handle($post, $file))
        ->toThrow(DisallowedMimeException::class);

    expect(Attachment::count())->toBe(0);
});

it('stores the file, persists the record, and dispatches AttachmentUploaded', function (): void {
    config()->set('attachments.storage.default_disk', 'local');
    Storage::fake('local');
    Event::fake([AttachmentUploaded::class]);

    $post = UploadTestPost::create();
    $file = UploadedFile::fake()->image('photo.png', 640, 480);

    $attachment = app(UploadAttachment::class)->handle($post, $file, ['caption' => 'Hello']);

    expect($attachment->exists)->toBeTrue()
        ->and($attachment->caption)->toBe('Hello')
        ->and($attachment->file_type)->toBe('image/png')
        ->and($attachment->is_default)->toBeTrue() // first attachment becomes default
        ->and($attachment->id)->toBe(basename(dirname($attachment->file_path))); // path id matches record id

    Storage::disk('local')->assertExists($attachment->file_path);
    Event::assertDispatched(AttachmentUploaded::class);
});

it('does not allow caller attributes to override trusted stored metadata', function (): void {
    config()->set('attachments.storage.default_disk', 'local');
    Storage::fake('local');

    $post = UploadTestPost::create();
    $file = UploadedFile::fake()->image('photo.png', 640, 480);

    $attachment = app(UploadAttachment::class)->handle($post, $file, [
        'caption' => 'Trusted caption',
        'file_path' => 'malicious/path.png',
        'file_type' => 'text/html',
        'file_size' => 1,
        'thumbnail_path' => 'malicious/thumb.jpg',
        'uploaded_by' => '01J00000000000000000000000',
    ]);

    expect($attachment->caption)->toBe('Trusted caption')
        ->and($attachment->file_path)->not->toBe('malicious/path.png')
        ->and($attachment->file_type)->toBe('image/png')
        ->and($attachment->file_size)->toBe($file->getSize())
        ->and($attachment->thumbnail_path)->not->toBe('malicious/thumb.jpg')
        ->and($attachment->uploaded_by)->toBeNull();

    Storage::disk('local')->assertExists($attachment->file_path);
});

it('defers thumbnail generation to the queue when configured', function (): void {
    config()->set('attachments.storage.default_disk', 'local');
    config()->set('attachments.thumbnails.queued', true);
    Storage::fake('local');
    Queue::fake();

    $post = UploadTestPost::create();
    $file = UploadedFile::fake()->image('photo.png', 640, 480);

    $attachment = app(UploadAttachment::class)->handle($post, $file);

    expect($attachment->thumbnail_path)->toBeNull();
    Storage::disk('local')->assertExists($attachment->file_path);
    Queue::assertPushed(GenerateAttachmentThumbnailJob::class, fn (GenerateAttachmentThumbnailJob $job): bool => $job->attachmentId === $attachment->id);
});

it('does not queue pdf thumbnails when pdf thumbnail support is disabled', function (): void {
    config()->set('attachments.storage.default_disk', 'local');
    config()->set('attachments.thumbnails.queued', true);
    config()->set('attachments.thumbnails.pdf_enabled', false);
    Storage::fake('local');
    Queue::fake();

    $post = UploadTestPost::create();
    $file = UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf');

    $attachment = app(UploadAttachment::class)->handle($post, $file);

    expect($attachment->thumbnail_path)->toBeNull()
        ->and($attachment->file_type)->toBe('application/pdf');

    Queue::assertNotPushed(GenerateAttachmentThumbnailJob::class);
});
