<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(SyncPermissions $sync): void
    {
        $sync->handle();
        User::query()->firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin', 'password' => 'password'])
            ->assignRole(SyncPermissions::superAdminRole());

        foreach (range(1, 30) as $i) {
            Product::query()->create([
                'name' => fake()->unique()->words(2, true),
                'sku' => sprintf('SKU-%03d', $i),
                'price' => fake()->randomFloat(2, 1, 200),
                'status' => fake()->randomElement(['draft', 'live']),
                'active' => fake()->boolean(),
                'released_on' => fake()->optional()->date(),
            ]);
        }
    }
}
