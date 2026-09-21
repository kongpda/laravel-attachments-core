<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Kongpda\LaravelAttachments\Actions\UploadAttachment;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Tests\Fixtures\Post;
use Kongpda\LaravelAttachments\Tests\Fixtures\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Post::migrate();
    Storage::fake('local');
    config()->set('attachments.storage.default_disk', 'local');
    config()->set('attachments.thumbnails.queued', false);

    Gate::define('view', fn (User $user, Post $post): bool => $post->user_id === $user->id);
});

function attachmentOwnedBy(int $userId, ?UploadedFile $file = null): Attachment
{
    return app(UploadAttachment::class)->handle(
        Post::create(['user_id' => $userId]),
        $file ?? UploadedFile::fake()->image('photo.png', 640, 480),
    );
}

it('serves a file to a user who may view its parent, with headers that stop it running as the app', function (): void {
    $attachment = attachmentOwnedBy(1);

    $response = actingAs(User::withId(1))->get(route('attachment.download', $attachment));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Length', (string) $attachment->file_size);

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->streamedContent())->toBe(Storage::disk('local')->get($attachment->file_path));
});

it('forces a download for types a browser would otherwise render', function (): void {
    config()->set('attachments.uploads.allowed_mimes', []);

    $attachment = attachmentOwnedBy(1, UploadedFile::fake()->createWithContent('page.html', '<html><script>alert(1)</script></html>'));

    $response = actingAs(User::withId(1))->get(route('attachment.download', $attachment));

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('refuses a user who may not view the parent', function (): void {
    $attachment = attachmentOwnedBy(1);

    actingAs(User::withId(2))->get(route('attachment.download', $attachment))->assertForbidden();
    actingAs(User::withId(2))->get(route('attachment.thumbnail', $attachment))->assertForbidden();
});

it('refuses a guest', function (): void {
    getJson(route('attachment.download', attachmentOwnedBy(1)))->assertUnauthorized();
});

it('hides an attachment whose parent is gone rather than serving an unowned file', function (): void {
    $attachment = attachmentOwnedBy(1);
    $attachment->attachable->delete();

    actingAs(User::withId(1))->get(route('attachment.download', $attachment))->assertNotFound();
});

it('answers a repeat request with 304 without reading storage', function (): void {
    $attachment = attachmentOwnedBy(1);
    $etag = actingAs(User::withId(1))->get(route('attachment.download', $attachment))->headers->get('ETag');

    // With the file gone, only a response that never touched the disk can succeed.
    Storage::disk('local')->delete($attachment->file_path);

    get(route('attachment.download', $attachment), ['If-None-Match' => $etag])->assertStatus(304);
});

it('still authorizes a conditional request', function (): void {
    $attachment = attachmentOwnedBy(1);
    $etag = actingAs(User::withId(1))->get(route('attachment.download', $attachment))->headers->get('ETag');

    actingAs(User::withId(2))
        ->get(route('attachment.download', $attachment), ['If-None-Match' => $etag])
        ->assertForbidden();
});

it('serves the thumbnail as a jpeg and 404s when there is none', function (): void {
    $withThumbnail = attachmentOwnedBy(1);
    $without = attachmentOwnedBy(1, UploadedFile::fake()->create('notes.txt', 4, 'text/plain'));

    expect($withThumbnail->thumbnail_path)->not->toBeNull();

    actingAs(User::withId(1))->get(route('attachment.thumbnail', $withThumbnail))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    actingAs(User::withId(1))->get(route('attachment.thumbnail', $without))->assertNotFound();
});

it('returns 404 when the record outlived its file', function (): void {
    $attachment = attachmentOwnedBy(1);
    Storage::disk('local')->delete($attachment->file_path);

    actingAs(User::withId(1))->get(route('attachment.download', $attachment))->assertNotFound();
});
