<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('department', 255)->nullable();
            $table->string('position', 255)->nullable();
            $table->date('join_date')->nullable();
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->decimal('allowance', 10, 2)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_account', 255)->nullable();
            $table->timestamps();
        });

        // Migrate existing data before dropping columns
        DB::statement('
            INSERT INTO user_staff_profiles (user_id, department, position, join_date, base_salary, allowance, bank_name, bank_account, created_at, updated_at)
            SELECT id, department, position, join_date, base_salary, allowance, bank_name, bank_account, NOW(), NOW()
            FROM users
            WHERE department IS NOT NULL
               OR position IS NOT NULL
               OR join_date IS NOT NULL
               OR base_salary IS NOT NULL
               OR allowance IS NOT NULL
               OR bank_name IS NOT NULL
               OR bank_account IS NOT NULL
        ');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'department',
                'position',
                'join_date',
                'base_salary',
                'allowance',
                'bank_name',
                'bank_account',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('department', 255)->nullable();
            $table->string('position', 255)->nullable();
            $table->date('join_date')->nullable();
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->decimal('allowance', 10, 2)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_account', 255)->nullable();
        });

        // Restore data
        DB::statement('
            UPDATE users u
            JOIN user_staff_profiles p ON p.user_id = u.id
            SET u.department = p.department,
                u.position = p.position,
                u.join_date = p.join_date,
                u.base_salary = p.base_salary,
                u.allowance = p.allowance,
                u.bank_name = p.bank_name,
                u.bank_account = p.bank_account
        ');

        Schema::dropIfExists('user_staff_profiles');
    }
};
