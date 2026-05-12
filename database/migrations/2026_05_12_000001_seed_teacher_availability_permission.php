<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'teacher_availability.view'],
            ['name' => 'teacher_availability.view']
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'teacher_availability.view')->delete();
    }
};
