<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\AdmissionApplication;
use App\Models\Promotion;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Services\DashboardStatsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardObserver
{
    public function created(Model $model): void
    {
        $this->changed($model, true);
    }

    public function updated(Model $model): void
    {
        $this->changed($model, false);
    }

    private function changed(Model $model, bool $created): void
    {
        if (! $created && ! $model->wasChanged()) {
            return;
        }
        $description = null;
        $type = 'student';
        if ($model instanceof Student) {
            $description = 'Student '.($created ? 'created' : 'updated').': '.$model->first_name.' '.$model->last_name;
        }
        if ($model instanceof \App\Models\Teacher) {
            $description = 'Teacher '.($created ? 'created' : 'updated').': '.$model->name;
            $type = 'teacher';
        }
        if ($model instanceof \App\Models\Exam && $created) {
            $description = 'Exam created: '.$model->name;
            $type = 'exam';
        }
        if ($model instanceof Promotion && $created) {
            $description = 'Promotion recorded: '.(Promotion::ACTIONS[$model->action] ?? $model->action).' (student #'.$model->student_id.').';
            $type = 'promotion';
        }
        if ($model instanceof AdmissionApplication && $model->wasChanged('status') && $model->status === 'approved') {
            $description = 'Admission approved: '.$model->applicant_name;
            $type = 'admission';
        }
        if ($model instanceof SchoolEvent) {
            $description = 'Calendar event '.($created ? 'created' : 'updated').': '.$model->title;
            $type = 'event';
        }
        $this->record($model, $description, $type);
    }

    public function deleted(Model $model): void
    {
        $description = $model instanceof Student ? 'Student deleted: '.$model->first_name.' '.$model->last_name : ($model instanceof SchoolEvent ? 'Calendar event deleted: '.$model->title : null);
        $this->record($model, $description, $model instanceof SchoolEvent ? 'event' : 'student');
    }

    public function restored(Model $model): void
    {
        $this->record($model, $model instanceof Student ? 'Student restored: '.$model->first_name.' '.$model->last_name : null, 'student');
    }

    private function record(Model $model, ?string $description, string $type): void
    {
        if ($description && Schema::hasTable('activities')) {
            Activity::create(['description' => trim($description), 'type' => $type, 'subject' => $model->getTable().':'.$model->getKey(), 'user_id' => auth()->id(), 'created_at' => now()]);
        }
        DashboardStatsService::invalidate();
        // Upserts do not emit Eloquent events; their services invalidate explicitly.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => DashboardStatsService::invalidate());
        }
    }
}
