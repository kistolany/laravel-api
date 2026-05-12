<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_makeup_sessions', function (Blueprint $table) {
            // The schedule the teacher actually teaches in for the makeup
            // (may differ from schedule_id which is the original absent schedule)
            $table->foreignId('makeup_schedule_id')
                ->nullable()
                ->after('schedule_id')
                ->constrained('class_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_makeup_sessions', function (Blueprint $table) {
            $table->dropForeign(['makeup_schedule_id']);
            $table->dropColumn('makeup_schedule_id');
        });
    }
};
