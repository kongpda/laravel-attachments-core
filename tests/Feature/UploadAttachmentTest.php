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

it('refuses browser-executable files with the shipped configuration', function (Closure $file): void {
    // Served from the app's origin, either of these runs script as the app.
    Storage::fake('local');

    $post = UploadTestPost::create();

    expect(fn () => app(UploadAttachment::class)->handle($post, $file()))
        ->toThrow(DisallowedMimeException::class);

    expect(Attachment::count())->toBe(0);
})->with([
    'html' => [fn () => UploadedFile::fake()->createWithContent('page.html', '<html><script>alert(1)</script></html>')],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'html claiming to be a png' => [fn () => new UploadedFile(
        tap(tempnam(sys_get_temp_dir(), 'att'), fn (string $path) => file_put_contents($path, '<html><script>alert(1)</script></html>')),
        'photo.png', 'image/png', null, true,
    )],
]);

it('ignores a disk smuggled in through the attributes', function (): void {
    // Attributes are often request input; the disk decides where bytes land.
    config()->set('attachments.storage.default_disk', 'local');
    Storage::fake('local');
    Storage::fake('public');

    $attachment = app(UploadAttachment::class)->handle(
        UploadTestPost::create(),
        UploadedFile::fake()->image('photo.png'),
        ['disk' => 'public'],
    );

    expect($attachment->disk)->toBe('local');
    Storage::disk('public')->assertDirectoryEmpty('');
});

it('skips the thumbnail for an image that declares more pixels than allowed', function (): void {
    config()->set('attachments.storage.default_disk', 'local');
    config()->set('attachments.thumbnails.queued', false);
    config()->set('attachments.thumbnails.max_source_pixels', 10_000);
    Storage::fake('local');

    $attachment = app(UploadAttachment::class)->handle(
        UploadTestPost::create(),
        UploadedFile::fake()->image('huge.png', 200, 200),
    );

    expect($attachment->thumbnail_path)->toBeNull();
});

it('cannot be repointed at another file or owner through mass assignment', function (): void {
    config()->set('attachments.storage.default_disk', 'local');
    Storage::fake('local');

    $attachment = app(UploadAttachment::class)->handle(UploadTestPost::create(), UploadedFile::fake()->image('photo.png'));
    $original = $attachment->refresh()->only(['file_path', 'disk', 'attachable_id', 'uploaded_by']);

    $attachment->update([
        'caption' => 'Renamed',
        'file_path' => '../.env',
        'disk' => 'public',
        'attachable_id' => '999',
        'uploaded_by' => '01J00000000000000000000000',
    ]);

    expect($attachment->refresh()->caption)->toBe('Renamed')
        ->and($attachment->only(['file_path', 'disk', 'attachable_id', 'uploaded_by']))->toBe($original);
});

it('hands out the authorised download route unless a disk is opted in to direct urls', function (): void {
    config()->set('attachments.storage.default_disk', 'public');
    config()->set('attachments.thumbnails.queued', false);
    Storage::fake('public');

    $attachment = app(UploadAttachment::class)->handle(UploadTestPost::create(), UploadedFile::fake()->image('photo.png'));

    expect($attachment->getUrl())->toBe(route('attachment.download', $attachment))
        ->and($attachment->getThumbnailUrl())->toBe(route('attachment.thumbnail', $attachment));

    config()->set('attachments.storage.direct_url_disks', ['public']);

    expect($attachment->getUrl())->toBe(Storage::disk('public')->url($attachment->file_path));
});
