<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 20);
            $table->foreignId('major_id')->constrained('majors');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('phone_number', 50)->nullable();
            $table->string('telegram', 255)->nullable();
            $table->string('image')->nullable();
            $table->text('address');
            $table->string('role')->default('Teacher');
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['major_id', 'subject_id']);
            $table->index(['email', 'is_verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
