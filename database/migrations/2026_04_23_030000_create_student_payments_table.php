<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('invoice_no')->nullable()->unique();
            $table->string('academic_year')->nullable();
            $table->string('term')->nullable();
            $table->string('payment_plan', 40)->default('PAY_FULL');
            $table->string('payment_type')->default('Tuition Fee');
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->string('reference_no')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index(['academic_year', 'term']);
            $table->index('payment_plan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_payments');
    }
};
