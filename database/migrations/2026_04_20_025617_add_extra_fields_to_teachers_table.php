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
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('teacher_id', 20)->nullable()->unique()->after('id');
            $table->date('dob')->nullable()->after('gender');
            $table->string('nationality', 100)->nullable()->after('dob');
            $table->string('religion', 100)->nullable()->after('nationality');
            $table->string('marital_status', 20)->nullable()->after('religion');
            $table->string('national_id', 50)->nullable()->after('marital_status');
            $table->string('position', 50)->nullable()->after('address');
            $table->string('degree', 20)->nullable()->after('position');
            $table->string('specialization', 255)->nullable()->after('degree');
            $table->string('contract_type', 20)->nullable()->after('specialization');
            $table->string('salary_type', 20)->nullable()->after('contract_type');
            $table->decimal('salary', 10, 2)->nullable()->after('salary_type');
            $table->integer('experience')->nullable()->after('salary');
            $table->date('join_date')->nullable()->after('experience');
            $table->string('emergency_name', 255)->nullable()->after('join_date');
            $table->string('emergency_phone', 50)->nullable()->after('emergency_name');
            $table->text('note')->nullable()->after('emergency_phone');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn([
                'teacher_id', 'dob', 'nationality', 'religion', 'marital_status',
                'national_id', 'position', 'degree', 'specialization', 'contract_type',
                'salary_type', 'salary', 'experience', 'join_date',
                'emergency_name', 'emergency_phone', 'note',
            ]);
        });
    }
};
