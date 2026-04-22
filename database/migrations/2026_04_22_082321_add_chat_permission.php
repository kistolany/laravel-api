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
        Schema::table('permissions', function (Blueprint $table) {
            // Check if it already exists to avoid duplicates
            if (DB::table('permissions')->where('name', 'chat.view')->count() === 0) {
                DB::table('permissions')->insert([
                    'name' => 'chat.view',
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('name', 'chat.view')->delete();
    }
};
