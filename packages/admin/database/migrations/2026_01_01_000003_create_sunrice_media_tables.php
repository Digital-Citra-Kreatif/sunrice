<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The media library: folders (a parent_id tree) and files. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_media_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('sunrice_media_folders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sunrice_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('sunrice_media_folders')->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->json('sizes');
            $table->unsignedInteger('version')->default(1);
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path']);
            $table->index(['folder_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_media');
        Schema::dropIfExists('sunrice_media_folders');
    }
};
