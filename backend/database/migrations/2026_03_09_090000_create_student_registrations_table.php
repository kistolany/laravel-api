<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('high_school_name')->nullable();
            $table->string('high_school_province')->nullable();
            $table->integer('bacii_exam_year')->nullable();
            $table->string('bacii_grade', 10)->nullable();
            $table->string('target_degree')->nullable();
            $table->boolean('diploma_attached')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registrations');
    }
};
