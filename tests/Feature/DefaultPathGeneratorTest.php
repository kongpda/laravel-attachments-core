<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Kongpda\LaravelAttachments\PathGenerators\DefaultPathGenerator;

it('builds deterministic file and thumbnail paths from the morph alias and attachment id', function (): void {
    $attachable = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'document';
        }
    };

    $paths = (new DefaultPathGenerator)->pathsForUpload($attachable, '01TESTATTACHMENT', 'Annual Report.pdf');

    expect($paths['file_path'])->toStartWith('attachments/document/01TESTATTACHMENT/')
        ->and($paths['thumbnail_path'])->toStartWith('attachments/document/01TESTATTACHMENT/thumbnail/');
});

it('roots a morph class under its configured prefix so types can live in separate directories', function (): void {
    config()->set('attachments.storage.path_prefixes', ['document' => '/finance/']);

    $attachable = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'document';
        }
    };

    $paths = (new DefaultPathGenerator)->pathsForUpload($attachable, '01TESTATTACHMENT', 'Annual Report.pdf');

    expect($paths['file_path'])->toStartWith('finance/document/01TESTATTACHMENT/');
});
