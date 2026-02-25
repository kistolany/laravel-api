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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('full_name_kh');         
            $table->string('full_name_en');
            $table->string('gender'); 
            $table->string('dob');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('id_card_number');
            $table->string('image')->nullable();
            $table->boolean('short_docs_status');
            $table->text('other_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
