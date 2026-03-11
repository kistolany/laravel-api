<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->date('session_date');
            $table->unsignedInteger('session_number');
            $table->string('academic_year')->nullable();
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->foreignId('major_id')->nullable()->constrained('majors')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['class_id', 'subject_id', 'session_date', 'session_number'],
                'attendance_sessions_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
