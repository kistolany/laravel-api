<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionRepairSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Student role has necessary permissions
        $studentRole = Role::where('name', 'Student')->first();
        if ($studentRole) {
            $perms = Permission::whereIn('name', [
                'student.view',
                'leave_request.view',
                'leave_request.create',
                'class_schedule.view',
                'academic_info.view',
                'subject_classroom.view',
                'subject_classroom.submit',
            ])->pluck('id');
            
            $studentRole->permissions()->syncWithoutDetaching($perms);
        }

        // 2. Link lany123 to WAITING PAYER A (ID 12)
        $user = User::where('username', 'lany123')->first();
        if ($user) {
            $user->update(['student_id' => 12]);
        }
    }
}
