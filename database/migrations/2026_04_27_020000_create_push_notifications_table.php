<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('body', 1000);
            $table->enum('audience', ['all', 'admin', 'teacher', 'staff'])->default('all');
            $table->enum('priority', ['normal', 'info', 'warning', 'urgent'])->default('normal');
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();

            $table->index('audience');
            $table->index('priority');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
