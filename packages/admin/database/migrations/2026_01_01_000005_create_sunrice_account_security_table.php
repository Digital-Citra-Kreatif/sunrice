<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Accounts an administrator locked or signed out everywhere (see AccountGuard). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_account_security', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 64)->unique();
            $table->timestamp('locked_at')->nullable()->index();
            $table->string('locked_by')->nullable();
            $table->string('locked_reason')->nullable();
            $table->timestamp('signed_out_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_account_security');
    }
};
