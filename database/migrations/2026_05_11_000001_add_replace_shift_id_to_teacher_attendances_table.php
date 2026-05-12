<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            // Records which shift the substitute teacher came from when they teach
            // a different shift's class (e.g. evening teacher covering morning class).
            $table->foreignId('replace_shift_id')
                ->nullable()
                ->after('replace_subject_id')
                ->constrained('shifts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropForeign(['replace_shift_id']);
            $table->dropColumn('replace_shift_id');
        });
    }
};
