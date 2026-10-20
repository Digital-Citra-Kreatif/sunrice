<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each user's own choices in the panel (a table's columns and page size), by key.
        Schema::create('sunrice_user_preferences', function (Blueprint $table): void {
            $table->id();
            // The app's users table is the app's: no foreign key.
            $table->string('user_id', 64);
            $table->string('key', 100);
            $table->json('value');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_user_preferences');
    }
};
