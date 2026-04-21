<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'name_en') && !Schema::hasColumn('shifts', 'name')) {
                $table->renameColumn('name_en', 'name');
            } elseif (Schema::hasColumn('shifts', 'name_eg') && !Schema::hasColumn('shifts', 'name')) {
                $table->renameColumn('name_eg', 'name');
            }

            if (Schema::hasColumn('shifts', 'name_kh')) {
                $table->dropColumn('name_kh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'name') && !Schema::hasColumn('shifts', 'name_en')) {
                $table->renameColumn('name', 'name_en');
            }

            if (!Schema::hasColumn('shifts', 'name_kh')) {
                $table->string('name_kh')->after('id');
            }
        });
    }
};
