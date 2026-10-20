<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A test model with a trash. */
final class Note extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
