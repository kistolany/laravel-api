<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            // NULL = broadcast to audience role; set = only this specific user sees it
            $table->unsignedBigInteger('target_user_id')->nullable()->after('sent_by');
            $table->index('target_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropIndex(['target_user_id']);
            $table->dropColumn('target_user_id');
        });
    }
};
