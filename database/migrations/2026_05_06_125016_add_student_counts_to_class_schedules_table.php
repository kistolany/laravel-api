<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_male')->default(0)->after('day_of_week');
            $table->unsignedSmallInteger('total_female')->default(0)->after('total_male');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropColumn(['total_male', 'total_female']);
        });
    }
};
