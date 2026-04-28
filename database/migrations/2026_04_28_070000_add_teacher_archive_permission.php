<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            ['name' => 'teacher.archive.view'],
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'teacher.archive.view')
            ->value('id');

        $adminRoleId = DB::table('roles')
            ->where('name', 'Admin')
            ->value('id');

        if ($permissionId && $adminRoleId) {
            DB::table('role_permissions')->insertOrIgnore([
                [
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ],
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'teacher.archive.view')
            ->value('id');

        if ($permissionId) {
            DB::table('role_permissions')
                ->where('permission_id', $permissionId)
                ->delete();
        }

        DB::table('permissions')
            ->where('name', 'teacher.archive.view')
            ->delete();
    }
};
