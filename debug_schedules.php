<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassSchedule;

$schedules = ClassSchedule::all();
echo "Total Schedules: " . $schedules->count() . "\n";
foreach ($schedules as $s) {
    echo "ID: {$s->id}, Class: {$s->class_id}, Subject: {$s->subject_id}, Teacher: {$s->teacher_id}\n";
}
