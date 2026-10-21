<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The activity log is filtered by IP address (exactly, or by its start:
 * "192.168."): an index keeps that fast on a long log. The address was
 * always a column of its own (`ip_address`), so there's nothing to fill in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunrice_activity', function (Blueprint $table): void {
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('sunrice_activity', function (Blueprint $table): void {
            $table->dropIndex(['ip_address']);
        });
    }
};
