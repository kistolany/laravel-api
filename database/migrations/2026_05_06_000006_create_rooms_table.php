<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('building', 100)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('type', 50)->nullable()->comment('classroom,lab,hall,office');
            $table->string('note', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrate existing plain-text room values into the rooms table
        DB::statement("
            INSERT INTO rooms (name, is_active, created_at, updated_at)
            SELECT DISTINCT TRIM(room), 1, NOW(), NOW()
            FROM class_schedules
            WHERE room IS NOT NULL AND TRIM(room) <> ''
        ");

        Schema::table('class_schedules', function (Blueprint $table): void {
            $table->foreignId('room_id')->nullable()->after('shift_id')->constrained('rooms')->nullOnDelete();
        });

        // Link existing schedules to the new room rows
        DB::statement("
            UPDATE class_schedules cs
            JOIN rooms r ON r.name = TRIM(cs.room)
            SET cs.room_id = r.id
            WHERE cs.room IS NOT NULL AND TRIM(cs.room) <> ''
        ");
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table): void {
            $table->dropForeign(['room_id']);
            $table->dropColumn('room_id');
        });

        Schema::dropIfExists('rooms');
    }
};
