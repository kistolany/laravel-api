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
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('staff_id');
            $table->string('position')->nullable()->after('department');
            $table->date('join_date')->nullable()->after('position');
            $table->decimal('base_salary', 12, 2)->nullable()->after('join_date');
            $table->decimal('allowance', 12, 2)->nullable()->after('base_salary');
            $table->string('bank_name')->nullable()->after('allowance');
            $table->string('bank_account')->nullable()->after('bank_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'department',
                'position',
                'join_date',
                'base_salary',
                'allowance',
                'bank_name',
                'bank_account'
            ]);
        });
    }
};
