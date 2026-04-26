<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_scores', function (Blueprint $table) {
            $table->string('assignment_score_source', 20)->nullable()->after('assignment_score');
            $table->string('midterm_score_source', 20)->nullable()->after('midterm_score');
            $table->string('final_score_source', 20)->nullable()->after('final_score');
        });
    }

    public function down(): void
    {
        Schema::table('student_scores', function (Blueprint $table) {
            $table->dropColumn(['assignment_score_source', 'midterm_score_source', 'final_score_source']);
        });
    }
};
