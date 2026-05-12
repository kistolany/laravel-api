<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_makeup_sessions', function (Blueprint $table) {
            $table->id();

            // Which original schedule this makeup belongs to
            $table->foreignId('schedule_id')
                ->constrained('class_schedules')
                ->cascadeOnDelete();

            // The teacher doing the makeup (usually the same as schedule->teacher_id)
            $table->foreignId('teacher_id')
                ->constrained('teachers')
                ->cascadeOnDelete();

            // The actual makeup date (week 16, 17, …)
            $table->date('makeup_date');

            // Session slot being made up (1 or 2)
            $table->unsignedTinyInteger('makeup_session');

            // Which absent week (1-15) this makeup covers
            $table->unsignedTinyInteger('absent_week_number');

            // The exact date the teacher was originally absent
            $table->date('absent_date');

            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');

            $table->text('note')->nullable();

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // One makeup per absent week/session per schedule
            $table->unique(['schedule_id', 'absent_week_number', 'makeup_session'], 'makeup_unique');

            $table->index(['schedule_id', 'makeup_date']);
            $table->index(['teacher_id', 'makeup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_makeup_sessions');
    }
};
