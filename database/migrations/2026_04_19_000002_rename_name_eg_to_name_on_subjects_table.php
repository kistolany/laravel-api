<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->renameColumn('name_eg', 'name');
            $table->dropColumn('name_kh');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->renameColumn('name', 'name_eg');
            $table->string('name_kh')->nullable();
        });
    }
};
