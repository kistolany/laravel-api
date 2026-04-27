<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        'holiday.view',
        'holiday.create',
        'holiday.update',
        'holiday.delete',
        'notification.view',
        'notification.create',
        'notification.delete',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            $exists = DB::table('permissions')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('permissions')->insert(['name' => $name]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', $this->permissions)->delete();
    }
};
