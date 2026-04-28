<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->string('admission_path', 30)->default('NEW_ENTRY')->after('student_id');
            $table->string('previous_school_name')->nullable()->after('high_school_province');
            $table->string('previous_school_province')->nullable()->after('previous_school_name');
            $table->string('completed_year_level', 30)->nullable()->after('previous_school_province');
            $table->text('placement_notes')->nullable()->after('completed_year_level');
        });
    }

    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'admission_path',
                'previous_school_name',
                'previous_school_province',
                'completed_year_level',
                'placement_notes',
            ]);
        });
    }
};
