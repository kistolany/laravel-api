<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            if (!Schema::hasColumn('students', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->after('deleted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('students', 'delete_reason')) {
                $table->text('delete_reason')->nullable()->after('deleted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }

            if (Schema::hasColumn('students', 'delete_reason')) {
                $table->dropColumn('delete_reason');
            }

            if (Schema::hasColumn('students', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
