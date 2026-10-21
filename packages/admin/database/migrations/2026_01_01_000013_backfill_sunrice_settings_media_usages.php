<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Sunrice\Admin\Actions\Settings\RecordSettingsMedia;

/*
 * Data only: records the files the settings sections' media pickers hold
 * (the branding logo, the CMS's share image) as used, for sections saved
 * before settings recorded them, or saved since without a change. Nothing
 * to do on an empty database.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(RecordSettingsMedia::class)->all();
    }

    public function down(): void
    {
        // Nothing to undo: the usages stay right whichever way.
    }
};
