<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where media files are used: kept by the plugins that use them (MediaUsages).
        Schema::create('sunrice_media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('sunrice_media')->cascadeOnDelete();
            // What uses it: a type ("cms.entry"), its id and language ('' for none).
            $table->string('type', 64);
            $table->string('item_id', 64);
            $table->string('locale', 16)->default('');
            $table->string('label');
            $table->string('url', 2048)->nullable();
            $table->timestamps();
            $table->index(['type', 'item_id', 'locale'], 'sunrice_media_usages_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_media_usages');
    }
};
