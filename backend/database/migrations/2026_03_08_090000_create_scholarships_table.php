<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();

            // ===== Student Reference =====
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // ===== Student Basic Info =====
            $table->string('nationality')->nullable();
            $table->string('ethnicity')->nullable();

            // ===== Emergency Contact =====
            $table->string('emergency_name');
            $table->string('emergency_relation');
            $table->string('emergency_phone');
            $table->text('emergency_address')->nullable();

            // ===== BacII Information =====
            $table->string('grade')->nullable();
            $table->integer('exam_year')->nullable();

            // ===== Family Information =====
            $table->text('guardians_address')->nullable();
            $table->string('guardians_phone_number')->nullable();
            $table->string('guardians_email')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarships');
    }
};
