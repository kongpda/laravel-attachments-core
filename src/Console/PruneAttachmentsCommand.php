<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\StoredAttachment;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;

/**
 * A soft delete keeps the files so a restore still works, which means nothing
 * ever removes them. This is that something.
 */
class PruneAttachmentsCommand extends Command
{
    protected $signature = 'attachments:prune {--days= : Override attachments.prune.after_days}';

    protected $description = 'Permanently delete soft-deleted attachments, and their files, once they are old enough';

    public function handle(AttachmentStorage $storage): int
    {
        $days = (int) ($this->option('days') ?? AttachmentConfig::pruneAfterDays());
        $pruned = 0;

        AttachmentConfig::attachmentModel()::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($days))
            ->chunkById(200, function (iterable $attachments) use ($storage, &$pruned): void {
                /** @var Model&StoredAttachment $attachment */
                foreach ($attachments as $attachment) {
                    // A file that could not be removed keeps its row, so the
                    // next run still knows where to look.
                    if ($storage->deleteAttachmentFiles($attachment)) {
                        $attachment->forceDelete();
                        $pruned++;
                    }
                }
            });

        $this->info("Pruned {$pruned} attachment(s).");

        return self::SUCCESS;
    }
}
