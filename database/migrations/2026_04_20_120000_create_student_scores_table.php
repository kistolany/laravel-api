<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('academic_year', 20)->nullable();
            $table->string('year_level', 20)->nullable();
            $table->string('semester', 20)->nullable();
            $table->decimal('class_score', 5, 2)->default(0);
            $table->decimal('assignment_score', 5, 2)->default(0);
            $table->decimal('midterm_score', 5, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['student_id', 'class_id', 'subject_id'], 'student_scores_lookup_index');
            $table->unique(
                ['student_id', 'class_id', 'subject_id', 'academic_year', 'year_level', 'semester'],
                'student_scores_context_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_scores');
    }
};
