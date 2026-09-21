<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Providers;

use Illuminate\Support\Facades\Route;
use Kongpda\LaravelAttachments\Console\PruneAttachmentsCommand;
use Kongpda\LaravelAttachments\Contracts\AttachmentAuthorizer;
use Kongpda\LaravelAttachments\Contracts\AttachmentStorage;
use Kongpda\LaravelAttachments\Contracts\PathGenerator;
use Kongpda\LaravelAttachments\Support\AttachmentConfig;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AttachmentCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-attachments-core')
            ->hasConfigFile('attachments')
            ->hasCommand(PruneAttachmentsCommand::class);

        if (AttachmentConfig::loadMigrations()) {
            $package->hasMigration('create_attachments_table');
        }
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(PathGenerator::class, AttachmentConfig::pathGeneratorClass());
        $this->app->singleton(AttachmentStorage::class, AttachmentConfig::storageClass());
        $this->app->singleton(AttachmentAuthorizer::class, AttachmentConfig::authorizerClass());
    }

    public function bootingPackage(): void
    {
        if (AttachmentConfig::loadRoutes()) {
            Route::middleware(AttachmentConfig::routeMiddleware())
                ->prefix(AttachmentConfig::routePrefix())
                ->group(__DIR__.'/../../routes/attachments.php');
        }
    }
}
