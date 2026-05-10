<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['name' => 'Weekend 1', 'time_range' => '07:30 - 10:00'],
            ['name' => 'Weekend 2', 'time_range' => '11:00 - 14:30'],
            ['name' => 'Weekend 3', 'time_range' => '15:00 - 17:30'],
        ] as $shift) {
            DB::table('shifts')->updateOrInsert(
                ['name' => $shift['name']],
                ['time_range' => $shift['time_range'], 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('shifts')->whereIn('name', ['Weekend 1', 'Weekend 2', 'Weekend 3'])->delete();
    }
};
