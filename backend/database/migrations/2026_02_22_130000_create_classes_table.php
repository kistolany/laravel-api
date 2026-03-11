<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('major_id')->constrained('majors');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->string('academic_year');
            $table->unsignedTinyInteger('year_level');
            $table->unsignedTinyInteger('semester');
            $table->string('section', 10);
            $table->unsignedInteger('max_students')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
