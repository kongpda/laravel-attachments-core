<?php

declare(strict_types=1);

namespace Kongpda\LaravelAttachments\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    public static function withId(int $id): self
    {
        return (new self)->forceFill(['id' => $id]);
    }
}
