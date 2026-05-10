<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->foreignId('replace_teacher_id')
                  ->nullable()
                  ->after('note')
                  ->constrained('teachers')
                  ->nullOnDelete();
            $table->enum('replace_status', ['Present', 'Absent'])
                  ->nullable()
                  ->after('replace_teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropForeign(['replace_teacher_id']);
            $table->dropColumn(['replace_teacher_id', 'replace_status']);
        });
    }
};
