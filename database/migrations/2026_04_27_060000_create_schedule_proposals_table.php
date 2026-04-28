<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_proposals', function (Blueprint $table) {
            $table->id();

            // What the admin defined
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->enum('day_of_week', ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']);
            $table->string('room', 100)->nullable();
            $table->string('academic_year', 20);
            $table->unsignedTinyInteger('year_level');
            $table->unsignedTinyInteger('semester');

            // Who admin sent it to
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();

            // Teacher response
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->text('reject_reason')->nullable();
            $table->timestamp('responded_at')->nullable();

            // If accepted, this points to the created class_schedule
            $table->foreignId('schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_proposals');
    }
};
