<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'description' => 'Full system access'],
            ['name' => 'Staff', 'description' => 'Staff operations'],
            ['name' => 'Assistant', 'description' => 'Assistant staff operations'],
            ['name' => 'Teacher', 'description' => 'Teacher attendance and major listing access'],
            ['name' => 'OrderStaff', 'description' => 'Order staff operations'],
            ['name' => 'Viewer', 'description' => 'Read-only access'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
