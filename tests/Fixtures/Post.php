<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kongpda\LaravelAttachments\Models\Concerns\HasAttachments;

/**
 * A morph parent with an owner, so authorization has something to decide on.
 */
class Post extends Model
{
    use HasAttachments;

    protected $table = 'fixture_posts';

    protected $guarded = [];

    public static function migrate(): void
    {
        Schema::create('fixture_posts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }
}
