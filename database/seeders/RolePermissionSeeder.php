<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    private function grantPermissions(array $roleNames, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        Role::whereIn('name', $roleNames)
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));
    }

    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();

        if ($adminRole) {
            $permissionIds = Permission::pluck('id')->all();
            $adminRole->permissions()->sync($permissionIds);
        }

        $classroomStaffPermissions = [
            'subject_classroom.view',
            'subject_classroom.create',
            'subject_classroom.update',
            'subject_classroom.delete',
            'subject_classroom.grade',
            'subject_classroom.review',
        ];

        $this->grantPermissions(['Staff', 'Assistant', 'OrderStaff', 'Teacher'], $classroomStaffPermissions);
        $this->grantPermissions(['Student'], ['subject_classroom.view', 'subject_classroom.submit']);
    }
}
