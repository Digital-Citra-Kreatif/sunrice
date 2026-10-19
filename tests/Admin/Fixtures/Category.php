<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class Category extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
