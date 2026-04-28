<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->enum('day_of_week', ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']);
            $table->timestamps();

            // A teacher can only mark the same day+shift+subject once
            $table->unique(['teacher_id','subject_id','shift_id','day_of_week'], 'teacher_avail_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availabilities');
    }
};
