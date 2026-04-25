<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('homework_assignments', 'assessment_type')) {
                $table->string('assessment_type', 30)->default('homework')->after('teacher_id');
                $table->index(['class_id', 'subject_id', 'assessment_type'], 'hw_assessment_type_idx');
            }
        });

        if (Schema::hasColumn('homework_assignments', 'assessment_type')) {
            DB::table('homework_assignments')
                ->whereNull('assessment_type')
                ->update(['assessment_type' => 'homework']);
        }
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('homework_assignments', 'assessment_type')) {
                $table->dropIndex('hw_assessment_type_idx');
                $table->dropColumn('assessment_type');
            }
        });
    }
};
