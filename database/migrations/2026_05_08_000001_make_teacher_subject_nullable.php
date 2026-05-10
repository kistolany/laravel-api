<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $fallbackSubjectId = DB::table('subjects')->value('id');

        if ($fallbackSubjectId) {
            DB::table('teachers')
                ->whereNull('subject_id')
                ->update(['subject_id' => $fallbackSubjectId]);
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable(false)->change();
        });
    }
};
