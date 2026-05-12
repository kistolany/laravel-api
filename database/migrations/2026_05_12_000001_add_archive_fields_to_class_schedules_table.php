<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('end_date');
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            $table->text('delete_reason')->nullable()->after('deleted_by');

            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'deleted_by', 'delete_reason']);
        });
    }
};
