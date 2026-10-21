<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunrice_media', function (Blueprint $table): void {
            // Alternative text and caption in the content's other languages: {"id": {"alt": "…", "caption": "…"}}.
            $table->json('translations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sunrice_media', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
    }
};
