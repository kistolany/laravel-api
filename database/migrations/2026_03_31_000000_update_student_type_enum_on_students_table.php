<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE students SET student_type = 'PAY' WHERE student_type = 'pay'");
        DB::statement("UPDATE students SET student_type = 'PENDING' WHERE student_type = 'schoolathip'");
        DB::statement("UPDATE students SET student_type = NULL WHERE student_type IS NOT NULL AND student_type NOT IN ('PAY', 'PENDING', 'PASS', 'FAIL')");
        DB::statement("ALTER TABLE students MODIFY student_type ENUM('PAY', 'PENDING', 'PASS', 'FAIL') NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE students SET student_type = 'pay' WHERE student_type = 'PAY'");
        DB::statement("UPDATE students SET student_type = 'schoolathip' WHERE student_type IN ('PENDING', 'PASS', 'FAIL')");
        DB::statement("ALTER TABLE students MODIFY student_type ENUM('schoolathip', 'pay') NULL");
    }
};
