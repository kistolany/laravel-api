<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('subject_Code')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('subjects')
            ->whereNull('subject_Code')
            ->update(['subject_Code' => '']);

        Schema::table('subjects', function (Blueprint $table) {
            $table->string('subject_Code')->nullable(false)->change();
        });
    }
};
