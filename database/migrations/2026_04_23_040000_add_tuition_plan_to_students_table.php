<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'tuition_plan')) {
                $table->string('tuition_plan', 40)->nullable()->after('student_type');
                $table->index('tuition_plan', 'students_tuition_plan_idx');
            }

            if (!Schema::hasColumn('students', 'tuition_plan_assigned_at')) {
                $table->timestamp('tuition_plan_assigned_at')->nullable()->after('tuition_plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'tuition_plan')) {
                $table->dropIndex('students_tuition_plan_idx');
            }

            if (Schema::hasColumn('students', 'tuition_plan_assigned_at')) {
                $table->dropColumn('tuition_plan_assigned_at');
            }

            if (Schema::hasColumn('students', 'tuition_plan')) {
                $table->dropColumn('tuition_plan');
            }
        });
    }
};
