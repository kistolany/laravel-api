<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('who', 255);
            $table->string('action', 80);
            $table->string('module', 120);
            $table->text('description');
            $table->string('ip', 45)->nullable();
            $table->longText('before')->nullable();
            $table->longText('after')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('action');
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
