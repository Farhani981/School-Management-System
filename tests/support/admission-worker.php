<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = $argv[1];
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 5000]);
Illuminate\Support\Facades\DB::purge('sqlite');
if (($argv[2] ?? '') === 'init') {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    exit;
}
$student = app(App\Services\StudentRecords::class)->save(new App\Models\Student, [
    'first_name' => 'Concurrent', 'last_name' => 'Admission', 'father_name' => 'Test Guardian',
    'date_of_birth' => '2015-01-01', 'gender' => 'Male', 'phone' => '03000000000',
    'address' => 'Test address', 'admission_date' => '2026-09-01', 'roll_number' => $argv[2], 'status' => 'Active',
]);
echo $student->admission_no;
