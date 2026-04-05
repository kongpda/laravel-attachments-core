<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kongpda\LaravelAttachments\Actions\ServeAttachmentAction;

Route::get('/attachments/{attachment}/download', [ServeAttachmentAction::class, 'download'])
    ->name('attachment.download');

Route::get('/attachments/{attachment}/thumbnail', [ServeAttachmentAction::class, 'thumbnail'])
    ->name('attachment.thumbnail');
