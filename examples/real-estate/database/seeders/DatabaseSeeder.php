<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * A fresh install: the content model (database/structure.json). Content itself is written in the admin;
     * create the first super admin with `php artisan sunrice:make-admin`.
     */
    public function run(): void
    {
        $this->call(StructureSeeder::class);
    }
}
