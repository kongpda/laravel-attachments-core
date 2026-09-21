<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Actions\UploadAttachment;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Tests\Fixtures\Post;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    Post::migrate();
    Storage::fake('local');
    config()->set('attachments.storage.default_disk', 'local');
    config()->set('attachments.thumbnails.queued', false);
});

it('removes the files of attachments trashed longer than the retention window, and nothing newer', function (): void {
    $post = Post::create();
    $upload = fn (): Attachment => app(UploadAttachment::class)->handle($post, UploadedFile::fake()->image('photo.png', 64, 64));

    [$old, $recent, $live] = [$upload(), $upload(), $upload()];

    travelTo(now()->subDays(31), fn () => $old->delete());
    $recent->delete();

    artisan('attachments:prune')->assertSuccessful();

    Storage::disk('local')->assertMissing([$old->file_path, $old->thumbnail_path]);
    Storage::disk('local')->assertExists([$recent->file_path, $live->file_path]);

    expect(Attachment::withTrashed()->pluck('id')->all())->toEqualCanonicalizing([$recent->id, $live->id]);
});

it('honours a retention override', function (): void {
    $attachment = app(UploadAttachment::class)->handle(Post::create(), UploadedFile::fake()->image('photo.png', 64, 64));
    $attachment->delete();

    artisan('attachments:prune', ['--days' => 0])->assertSuccessful();

    Storage::disk('local')->assertMissing($attachment->file_path);
    expect(Attachment::withTrashed()->count())->toBe(0);
});
