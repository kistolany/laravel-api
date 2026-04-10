<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();

        if (!$adminRole) {
            return;
        }

        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password_hash' => Hash::make('123456'),
                'role_id' => $adminRole->id,
                'status' => 'Active',
            ]
        );
    }
}
