<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function permissions(): array
    {
        return [
            'subject_classroom.view',
            'subject_classroom.create',
            'subject_classroom.update',
            'subject_classroom.delete',
            'subject_classroom.submit',
            'subject_classroom.grade',
            'subject_classroom.review',
        ];
    }

    private function staffPermissions(): array
    {
        return [
            'subject_classroom.view',
            'subject_classroom.create',
            'subject_classroom.update',
            'subject_classroom.delete',
            'subject_classroom.grade',
            'subject_classroom.review',
        ];
    }

    private function grantPermissions(array $roleNames, array $permissionNames): void
    {
        $roleIds = DB::table('roles')->whereIn('name', $roleNames)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');

        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('role_permissions')->insertOrIgnore($rows);
        }
    }

    public function up(): void
    {
        $rows = array_map(
            fn (string $name) => ['name' => $name],
            $this->permissions()
        );

        DB::table('permissions')->insertOrIgnore($rows);

        $this->grantPermissions(['Admin'], $this->permissions());
        $this->grantPermissions(['Staff', 'Assistant', 'OrderStaff', 'Teacher'], $this->staffPermissions());
        $this->grantPermissions(['Student'], ['subject_classroom.view', 'subject_classroom.submit']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', $this->permissions())
            ->delete();
    }
};
