<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('year_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->unsignedTinyInteger('number')->nullable()->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->unsignedTinyInteger('number')->nullable()->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('year_levels')->insertOrIgnore(
            collect(range(1, 6))->map(fn (int $number) => [
                'name' => 'Year ' . $number,
                'code' => 'Y' . $number,
                'number' => $number,
                'sort_order' => $number,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        DB::table('academic_terms')->insertOrIgnore([
            [
                'name' => 'Semester 1',
                'code' => 'S1',
                'number' => 1,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Semester 2',
                'code' => 'S2',
                'number' => 2,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('year_levels');
    }
};
