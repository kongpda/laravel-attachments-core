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

    expect($paths['file_path'])->toStartWith('document/01TESTATTACHMENT/')
        ->and($paths['thumbnail_path'])->toStartWith('document/01TESTATTACHMENT/thumbnail/');
});
