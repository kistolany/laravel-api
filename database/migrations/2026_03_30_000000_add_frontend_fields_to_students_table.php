<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('student_type', ['schoolathip', 'pay'])->nullable()->after('id_card_number');
            $table->string('exam_place')->nullable()->after('student_type');
            $table->string('bacll_code')->nullable()->after('exam_place');
            $table->string('grade', 50)->after('bacll_code');
            $table->text('doc')->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'student_type',
                'exam_place',
                'bacll_code',
                'grade',
                'doc',
            ]);
        });
    }
};
