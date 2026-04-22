<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('attachment_url')->nullable();
            $table->string('attachment_name')->nullable();
            $table->dateTime('due_date');
            $table->unsignedInteger('max_score')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();

            $table->index(['class_id', 'subject_id'], 'hw_class_subject_idx');
            $table->index('teacher_id', 'hw_teacher_idx');
            $table->index('due_date', 'hw_due_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_assignments');
    }
};
