<?php

declare(strict_types=1);

use Kongpda\LaravelAttachments\Models\Attachment;

/**
 * Why this matters: the `documents` scope must stay inside the morph/relation
 * constraint. A bare `orWhereNull('group')` leaks null-group rows from every
 * other attachable into the result — a cross-tenant data exposure bug.
 */
it('keeps the documents scope from leaking rows across attachables', function (): void {
    Attachment::query()->forceCreate([
        'attachable_type' => 'post', 'attachable_id' => '1',
        'file_name' => 'a.pdf', 'file_path' => 'post/1/a.pdf',
        'file_type' => 'application/pdf', 'file_size' => 1, 'group' => null,
    ]);
    Attachment::query()->forceCreate([
        'attachable_type' => 'post', 'attachable_id' => '1',
        'file_name' => 'b.png', 'file_path' => 'post/1/b.png',
        'file_type' => 'image/png', 'file_size' => 1, 'group' => 'image',
    ]);
    Attachment::query()->forceCreate([
        'attachable_type' => 'user', 'attachable_id' => '9',
        'file_name' => 'c.txt', 'file_path' => 'user/9/c.txt',
        'file_type' => 'text/plain', 'file_size' => 1, 'group' => null,
    ]);

    $documents = Attachment::query()
        ->where('attachable_type', 'post')
        ->where('attachable_id', '1')
        ->documents()
        ->get();

    expect($documents)->toHaveCount(1)
        ->and($documents->first()->file_name)->toBe('a.pdf');
});
