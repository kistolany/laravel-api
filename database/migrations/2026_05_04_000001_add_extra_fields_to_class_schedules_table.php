<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('room');
            $table->date('start_date')->nullable()->after('code');
            $table->date('end_date')->nullable()->after('start_date');
            $table->unsignedSmallInteger('total_male')->default(0)->after('end_date');
            $table->unsignedSmallInteger('total_female')->default(0)->after('total_male');
            $table->unsignedSmallInteger('total_student')->default(0)->after('total_female');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropColumn(['code', 'start_date', 'end_date', 'total_male', 'total_female', 'total_student']);
        });
    }
};
