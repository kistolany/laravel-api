<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('homework_submissions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('feedback');
                $table->index('reviewed_at', 'hw_sub_reviewed_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('homework_submissions', 'reviewed_at')) {
                $table->dropIndex('hw_sub_reviewed_idx');
                $table->dropColumn('reviewed_at');
            }
        });
    }
};
