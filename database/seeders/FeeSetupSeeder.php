<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Services\DashboardStatsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeeSetupSeeder extends Seeder
{
    public function run(): void
    {
        Model::withoutEvents(function () {
            DB::transaction(function () {
                $types = [];
                foreach (['Admission Fee' => 'one_time', 'Tuition Fee' => 'monthly', 'Exam Fee' => 'quarterly', 'Annual Fee' => 'yearly', 'Computer Fee' => 'monthly', 'Transport Fee' => 'monthly'] as $name => $frequency) {
                    $types[$name] = FeeType::withTrashed()->firstOrCreate(['name' => $name], ['frequency' => $frequency, 'is_active' => true]);
                }
                $session = AcademicSession::where('is_current', true)->value('id');
                if (! $session) {
                    $this->command?->warn('No current session: fee types seeded; select a current session and rerun to add sample structures.');

                    return;
                }
                foreach (SchoolClass::orderBy('id')->get() as $class) {
                    $grade = preg_match('/(?:Class|Grade)\s+(\d+)/i', $class->name, $m) ? min(10, max(1, (int) $m[1])) : 1;
                    foreach (['Tuition Fee' => round(1500 + ($grade - 1) * 2000 / 9, 2), 'Exam Fee' => round(500 + ($grade - 1) * 500 / 9, 2)] as $name => $amount) {
                        if ($types[$name]->trashed() || ! $types[$name]->is_active) {
                            continue;
                        }
                        FeeStructure::firstOrCreate(['academic_session_id' => $session, 'school_class_id' => $class->id, 'fee_type_id' => $types[$name]->id], ['amount' => $amount]);
                    }
                }
            });
        });
        DashboardStatsService::invalidate();
    }
}
