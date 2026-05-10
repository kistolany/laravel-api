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
            if (Schema::hasColumn('teachers', 'is_verified')) {
                try {
                    DB::statement('ALTER TABLE teachers DROP INDEX teachers_email_is_verified_index');
                } catch (Throwable) {
                    // Index may not exist on older/local databases.
                }
            }

            $drop = array_values(array_filter(
                ['otp_code', 'otp_expires_at', 'is_verified', 'verified_at'],
                fn (string $column) => Schema::hasColumn('teachers', $column)
            ));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (!Schema::hasColumn('teachers', 'otp_code')) {
                $table->string('otp_code', 6)->nullable()->after('role');
            }
            if (!Schema::hasColumn('teachers', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            }
            if (!Schema::hasColumn('teachers', 'is_verified')) {
                $table->boolean('is_verified')->default(true)->after('otp_expires_at');
            }
            if (!Schema::hasColumn('teachers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_verified');
            }
        });
    }
};
