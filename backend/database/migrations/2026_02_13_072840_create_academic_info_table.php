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
        Schema::create('academic_info', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->integer('major_id');
            $table->integer('shift_id');
            $table->integer('batch_year');
            $table->string('stage');
            $table->string('study_days');
            $table->timestamps();
        });
    }

    /**ok
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_info');
    }
};
