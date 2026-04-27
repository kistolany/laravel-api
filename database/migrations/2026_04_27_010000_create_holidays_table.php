<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('type', ['public', 'national', 'religious', 'school', 'other'])->default('public');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('start_date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
