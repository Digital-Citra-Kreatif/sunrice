<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A test model with every kind of field. */
final class Product extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'released_on' => 'date', 'price' => 'float'];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public static function createTables(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->boolean('active')->default(false);
            $table->date('released_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->timestamps();
        });
    }
}
