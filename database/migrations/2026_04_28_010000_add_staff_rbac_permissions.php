<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function permissions(): array
    {
        return [
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
        ];
    }

    public function up(): void
    {
        $rows = array_map(
            fn (string $name) => ['name' => $name],
            $this->permissions()
        );

        DB::table('permissions')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', $this->permissions())
            ->delete();
    }
};
