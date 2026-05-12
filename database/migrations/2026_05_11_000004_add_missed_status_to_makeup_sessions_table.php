<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: modify enum to add 'missed'
        DB::statement("ALTER TABLE schedule_makeup_sessions MODIFY COLUMN status ENUM('scheduled','completed','cancelled','missed') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE schedule_makeup_sessions MODIFY COLUMN status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled'");
    }
};
