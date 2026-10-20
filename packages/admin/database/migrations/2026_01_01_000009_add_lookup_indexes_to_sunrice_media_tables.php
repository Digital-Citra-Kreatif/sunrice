<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Indexes for lookups the media library makes: usages by file (a foreign
 * key, which Postgres and SQLite don't index by themselves), the trash
 * (deleted_at) and files by type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunrice_media', function (Blueprint $table): void {
            $table->index('deleted_at');
            $table->index('mime_type');
        });
        Schema::table('sunrice_media_usages', function (Blueprint $table): void {
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::table('sunrice_media_usages', function (Blueprint $table): void {
            $table->dropIndex(['media_id']);
        });
        Schema::table('sunrice_media', function (Blueprint $table): void {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['mime_type']);
        });
    }
};
