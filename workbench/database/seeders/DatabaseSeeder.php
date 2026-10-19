<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(SyncPermissions $sync): void
    {
        $sync->handle();
        User::query()->firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin', 'password' => 'password'])
            ->assignRole(SyncPermissions::superAdminRole());
    }
}
