<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\RbacPermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = RbacPermissionCatalog::all();

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
    }
}
