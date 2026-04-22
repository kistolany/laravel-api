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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requester_type')->comment('student or teacher');
            $table->unsignedBigInteger('requester_id');
            $table->string('requester_name')->nullable();
            $table->string('requester_name_kh')->nullable();
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days')->default(1);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->timestamps();
            
            $table->index(['requester_type', 'requester_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
