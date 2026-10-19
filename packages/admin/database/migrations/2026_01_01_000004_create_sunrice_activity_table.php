<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The activity log: who changed what in the panel, and security events. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_activity', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 20)->index();
            $table->string('via', 100)->nullable();
            $table->string('user_id', 64)->nullable()->index();
            // Kept as written, so the log still reads well after the user is deleted.
            $table->string('user_name')->nullable();
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->index();
            $table->string('subject_id', 64)->nullable();
            $table->string('subject_label')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_activity');
    }
};
