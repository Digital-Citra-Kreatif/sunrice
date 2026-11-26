<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A test model whose selects choose several options (Select::multiple()). */
final class Palette extends Model
{
    protected $guarded = [];

    protected $casts = ['colours' => 'array', 'sizes' => 'array', 'shades' => 'array'];

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'palette_tag');
    }

    public static function createTables(): void
    {
        Schema::create('palettes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('colours')->nullable();
            $table->json('sizes')->nullable();
            $table->json('shades')->nullable();
            $table->string('finish')->nullable();
            $table->timestamps();
        });
        Schema::create('palette_tag', function (Blueprint $table) {
            $table->foreignId('palette_id');
            $table->foreignId('tag_id');
        });
    }
}
