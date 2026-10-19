<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Settings sections (Sunrice\Admin\Settings\Settings), one row each. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->longText('values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_settings');
    }
};
