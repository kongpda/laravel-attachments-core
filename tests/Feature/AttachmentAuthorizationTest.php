<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Models\Attachment;
use Kongpda\LaravelAttachments\Tests\Fixtures\Post;
use Kongpda\LaravelAttachments\Tests\Fixtures\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Post::migrate();

    Gate::define('view', fn (User $user, Post $post): bool => true);
    Gate::define('update', fn (User $user, Post $post): bool => $post->user_id === $user->id);
});

function attachmentOn(Post $post): Attachment
{
    return $post->createAttachment([
        'file_name' => 'a.png',
        'file_path' => 'attachments/post/a.png',
        'file_type' => 'image/png',
        'file_size' => 1,
        'disk' => 'local',
    ]);
}

it('lets whoever may update the parent manage its attachments, and nobody else', function (string $ability): void {
    $attachment = attachmentOn(Post::create(['user_id' => 1]));

    expect(User::withId(1)->can($ability, $attachment))->toBeTrue()
        ->and(User::withId(2)->can($ability, $attachment))->toBeFalse();
})->with(['update', 'delete', 'restore', 'forceDelete']);

it('lets a viewer of the parent view an attachment without being able to change it', function (): void {
    $attachment = attachmentOn(Post::create(['user_id' => 1]));

    expect(User::withId(2)->can('view', $attachment))->toBeTrue()
        ->and(User::withId(2)->can('update', $attachment))->toBeFalse();
});

it('denies everything on an attachment whose parent is gone', function (): void {
    $post = Post::create(['user_id' => 1]);
    $attachment = attachmentOn($post);
    $post->delete();

    expect(User::withId(1)->can('view', $attachment->fresh()))->toBeFalse()
        ->and(User::withId(1)->can('delete', $attachment->fresh()))->toBeFalse();
});

it('never grants listing or creating through the model policy', function (): void {
    expect(User::withId(1)->can('viewAny', Attachment::class))->toBeFalse()
        ->and(User::withId(1)->can('create', Attachment::class))->toBeFalse();
});

it('checks the requested ability on the parent before it may be managed', function (): void {
    $post = Post::create(['user_id' => 1]);
    $authorizer = app(AttachmentAuthorizer::class);

    actingAs(User::withId(1));
    $authorizer->authorizeManageAttachable($post);

    actingAs(User::withId(2));
    $authorizer->authorizeManageAttachable($post, 'view');

    expect(fn () => $authorizer->authorizeManageAttachable($post))->toThrow(AuthorizationException::class);
});
