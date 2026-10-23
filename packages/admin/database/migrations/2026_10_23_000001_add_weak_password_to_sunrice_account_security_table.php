<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Accounts asked to choose a stronger password (WeakPasswordAtLogin): a
 * fingerprint of the password hash they signed in with, so the request
 * outlives the session ("Remember me") and ends when the password changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunrice_account_security', function (Blueprint $table): void {
            $table->string('weak_password', 64)->nullable()->after('signed_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('sunrice_account_security', function (Blueprint $table): void {
            $table->dropColumn('weak_password');
        });
    }
};
