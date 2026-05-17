<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->enum('status', ['active', 'upcoming', 'closed'])->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        // Seed from existing distinct values in classes and student_scores
        $years = collect();

        if (Schema::hasColumn('classes', 'academic_year')) {
            $years = $years->merge(
                DB::table('classes')->whereNotNull('academic_year')->distinct()->pluck('academic_year')
            );
        }

        if (Schema::hasColumn('student_scores', 'academic_year')) {
            $years = $years->merge(
                DB::table('student_scores')->whereNotNull('academic_year')->distinct()->pluck('academic_year')
            );
        }

        $now = now();
        foreach ($years->filter()->unique()->sort()->values() as $name) {
            DB::table('academic_years')->insertOrIgnore([
                'name'       => (string) $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
