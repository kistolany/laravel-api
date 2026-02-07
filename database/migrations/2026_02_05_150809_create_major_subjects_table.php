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
        Schema::create('major_subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('major_id')->constrained();
            $table->foreignId('subject_id')->constrained();

            $table->tinyInteger('year_level')->unsigned()->comment('1-4');
            $table->tinyInteger('semester')->unsigned()->comment('1-2');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('major_subjects');
    }
};
