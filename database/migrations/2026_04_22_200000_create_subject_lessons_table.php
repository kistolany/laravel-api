<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_lessons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_url');
            $table->string('file_name');
            $table->string('file_type', 100);
            $table->unsignedInteger('file_size')->default(0);
            $table->date('lesson_date')->nullable();
            $table->timestamps();

            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();

            $table->index(['class_id', 'subject_id'], 'lessons_class_subject_idx');
            $table->index('teacher_id', 'lessons_teacher_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_lessons');
    }
};
