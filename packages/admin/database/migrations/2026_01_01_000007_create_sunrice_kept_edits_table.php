<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unsaved changes kept when someone took over an edit lock.
        Schema::create('sunrice_kept_edits', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64);
            $table->string('item_id', 64);
            $table->string('locale', 16)->default('');
            // The app's users table is the app's: no foreign key.
            $table->string('user_id', 64)->nullable();
            $table->string('user_name')->nullable();
            $table->string('taken_by')->nullable();
            $table->json('content');
            $table->timestamps();
            $table->index(['type', 'item_id', 'locale'], 'sunrice_kept_edits_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_kept_edits');
    }
};
