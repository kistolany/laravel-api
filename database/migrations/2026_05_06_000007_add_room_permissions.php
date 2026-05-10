<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function permissions(): array
    {
        return [
            'room.view',
            'room.manage',
        ];
    }

    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore(
            array_map(fn (string $name) => ['name' => $name], $this->permissions())
        );

        // Grant to Admin role automatically
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        if ($adminRoleId) {
            $permIds = DB::table('permissions')
                ->whereIn('name', $this->permissions())
                ->pluck('id');

            foreach ($permIds as $permId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'       => $adminRoleId,
                    'permission_id' => $permId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', $this->permissions())
            ->delete();
    }
};
