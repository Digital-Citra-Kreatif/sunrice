<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* AI access tokens: an AI agent works in the panel as the token's owner. Only a hash is stored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_ai_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 64)->index();
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique();
            $table->boolean('read_only')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_ai_tokens');
    }
};
