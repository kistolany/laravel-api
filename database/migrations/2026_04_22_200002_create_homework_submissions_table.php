<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('homework_id');
            $table->unsignedBigInteger('student_id');
            $table->string('file_url');
            $table->string('file_name');
            $table->string('file_type', 100);
            $table->unsignedInteger('file_size')->default(0);
            $table->text('note')->nullable();
            $table->dateTime('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->foreign('homework_id')->references('id')->on('homework_assignments')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();

            $table->unique(['homework_id', 'student_id'], 'hw_sub_unique');
            $table->index('student_id', 'hw_sub_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
    }
};
