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
        Schema::create('addresses', function (Blueprint $table) {
        $table->id();

        $table->string('student_id');
        $table->string('address_type');

        $table->string('house_number')->nullable();
        $table->string('street_number')->nullable();
        $table->string('village')->nullable();

        $table->foreignId('province_id')
            ->constrained('provinces');

        $table->foreignId('district_id')
            ->constrained('districts');
        
        $table->foreignId('commune_id')
            ->constrained('communes');

        $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
