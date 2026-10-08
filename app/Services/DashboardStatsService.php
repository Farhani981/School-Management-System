<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardStatsService
{
    public static function invalidate(): void
    {
        Cache::forever('dashboard:revision', (string) Str::uuid());
    }

    private function cached(string $widget, array $empty, callable $query): array
    {
        $revision = Cache::get('dashboard:revision', 'initial');

        return Cache::remember('dashboard:'.$revision.':'.now()->toDateString().':'.$widget, now()->addMinutes(5), function () use ($query, $empty, $widget) {
            try {
                return $query();
            } catch (QueryException $exception) {
                Log::warning('Dashboard widget unavailable', ['widget' => $widget, 'widget_error' => $exception->getCode()]);

                return $empty;
            }
        });
    }

    private function session(): array
    {
        if (! Schema::hasTable('academic_sessions') || ! Schema::hasColumn('academic_sessions', 'is_current')) {
            return ['scoped' => false, 'id' => null];
        }

        return ['scoped' => true, 'id' => DB::table('academic_sessions')->where('is_current', true)->orderByDesc('id')->value('id')];
    }

    private function studentsQuery(): Builder
    {
        $q = DB::table('students')->where('status', 'Active');
        if (Schema::hasColumn('students', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        $session = $this->session();
        if ($session['scoped'] && Schema::hasColumn('students', 'academic_session_id')) {
            $session['id'] ? $q->where('academic_session_id', $session['id']) : $q->whereRaw('1 = 0');
        }

        return $q;
    }

    private function activeQuery(string $table): Builder
    {
        $q = DB::table($table);
        if (Schema::hasColumn($table, 'status')) {
            $q->where('status', 'Active');
        }
        if (Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return $q;
    }

    public function students(): array
    {
        $empty = ['total' => 0, 'boys' => 0, 'girls' => 0, 'empty' => true];

        return $this->cached('students', $empty, function () use ($empty) {
            if (! Schema::hasTable('students')) {
                return $empty;
            }
            $groups = $this->studentsQuery()->select('gender')->selectRaw('COUNT(*) AS total')->groupBy('gender')->pluck('total', 'gender');

            return ['total' => (int) $groups->sum(), 'boys' => (int) $groups->get('Male', 0), 'girls' => (int) $groups->get('Female', 0), 'empty' => $groups->sum() === 0];
        });
    }

    public function teachers(): array
    {
        $empty = ['total' => 0, 'assigned' => 0, 'empty' => true];

        return $this->cached('teachers', $empty, function () use ($empty) {
            if (! Schema::hasTable('teachers')) {
                return $empty;
            }
            $total = $this->activeQuery('teachers')->count();

            $assigned = 0;
            if (Schema::hasTable('teacher_assignments')) {
                $session = $this->session();
                $assigned = $this->activeQuery('teachers')->whereIn('id', DB::table('teacher_assignments')->select('teacher_id')
                    ->when($session['scoped'], fn ($q) => $q->where('session_id', $session['id'])))->count();
            }
            return ['total' => $total, 'assigned' => $assigned, 'empty' => $total === 0];
        });
    }

    public function classes(): array
    {
        $empty = ['total' => 0, 'sections' => 0, 'empty' => true];

        return $this->cached('classes', $empty, function () use ($empty) {
            if (! Schema::hasTable('school_classes')) {
                return $empty;
            }
            $total = $this->activeQuery('school_classes')->count();
            $sections = Schema::hasTable('school_sections') ? $this->activeQuery('school_sections')->count() : 0;

            return ['total' => $total, 'sections' => $sections, 'empty' => $total === 0];
        });
    }

    public function attendance(): array
    {
        $empty = ['total' => 0, 'marked' => 0, 'percentage' => 0, 'empty' => true, 'counts' => ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0], 'rows' => [], 'message' => 'Attendance not marked yet'];
        foreach (['present' => 'green', 'absent' => 'coral', 'late' => 'amber', 'leave' => 'blue'] as $status => $tone) {
            $empty['rows'][] = ['label' => ucfirst($status), 'count' => 0, 'tone' => $tone, 'percentage' => 0];
        }

        return $this->cached('attendance', $empty, function () use ($empty) {
            $total = $this->students()['total'];
            $counts = $empty['counts'];
            $marked = 0;
            if (Schema::hasTable('student_attendances') && Schema::hasTable('students')) {
                $date = $this->column('student_attendances', ['date', 'attendance_date']);
                if ($date) {
                    $q = DB::table('student_attendances')->whereDate($date, now()->toDateString())->whereIn('student_id', $this->studentsQuery()->select('id'));
                    $session = $this->session();
                    if ($session['scoped'] && $session['id'] && Schema::hasColumn('student_attendances', 'academic_session_id')) {
                        $q->where(fn ($q) => $q->where('academic_session_id', $session['id'])->orWhereNull('academic_session_id'));
                    }
                    $groups = $q->selectRaw('LOWER(status) AS attendance_status, COUNT(*) AS total')->groupByRaw('LOWER(status)')->pluck('total', 'attendance_status');
                    $marked = (int) $groups->sum();
                    foreach ($counts as $status => $value) {
                        $counts[$status] = (int) $groups->get($status, 0);
                    }
                }
            }
            $rows = [];
            foreach (['present' => 'green', 'absent' => 'coral', 'late' => 'amber', 'leave' => 'blue'] as $status => $tone) {
                $rows[] = ['label' => ucfirst($status), 'count' => $counts[$status], 'tone' => $tone, 'percentage' => $total ? round($counts[$status] / $total * 100, 1) : 0];
            }

            return ['total' => $total, 'marked' => $marked, 'percentage' => $total ? round(($counts['present'] + $counts['late']) / $total * 100, 1) : 0, 'empty' => $marked === 0, 'counts' => $counts, 'rows' => $rows, 'message' => $marked ? 'Present and ready to learn' : 'Attendance not marked yet'];
        });
    }

    private function column(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function monthQuery(string $table, string $date, string $month): Builder
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $q = DB::table($table)->where($date, '>=', $start->toDateString())->where($date, '<', $start->addMonth()->toDateString());
        if (Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        $session = $this->session();
        if ($session['scoped'] && Schema::hasColumn($table, 'academic_session_id')) {
            $session['id'] ? $q->where('academic_session_id', $session['id']) : $q->whereRaw('1 = 0');
        } elseif ($session['scoped'] && Schema::hasColumn($table, 'student_id') && Schema::hasTable('students')) {
            $q->whereIn('student_id', $this->studentsQuery()->select('id'));
        }

        return $q;
    }

    public function fees(?string $month = null): array
    {
        $month ??= now()->format('Y-m');
        $empty = ['collected' => 0, 'pending' => 0, 'expenses' => 0, 'billed' => 0, 'percentage' => 0, 'empty' => true, 'month' => $month];

        return $this->cached('fees:'.$month, $empty, function () use ($empty, $month) {
            $result = $empty;
            if (Schema::hasTable('fee_vouchers')) {
                $date = $this->column('fee_vouchers', ['due_date', 'date', 'created_at']);
                $amount = $this->column('fee_vouchers', ['total_amount', 'amount']);
                if ($date && $amount) {
                    $q = $this->monthQuery('fee_vouchers', $date, $month);
                    $result['billed'] = (float) (clone $q)->sum($amount);
                    $balance = $this->column('fee_vouchers', ['balance', 'balance_amount', 'remaining_amount']);
                    $paid = $this->column('fee_vouchers', ['paid_amount', 'amount_paid']);
                    if ($balance) {
                        $result['pending'] = (float) (clone $q)->sum($balance);
                    } elseif ($paid) {
                        $result['pending'] = (float) (clone $q)->selectRaw("SUM(CASE WHEN $amount > COALESCE($paid, 0) THEN $amount - COALESCE($paid, 0) ELSE 0 END) AS pending")->value('pending');
                    } elseif (Schema::hasColumn('fee_vouchers', 'status')) {
                        $result['pending'] = (float) (clone $q)->whereIn('status', ['unpaid', 'pending', 'partial'])->sum($amount);
                    }
                }
            }
            if (Schema::hasTable('fee_payments')) {
                $date = $this->column('fee_payments', ['payment_date', 'paid_at', 'date', 'created_at']);
                $amount = $this->column('fee_payments', ['amount', 'paid_amount']);
                if ($date && $amount) {
                    $result['collected'] = (float) $this->monthQuery('fee_payments', $date, $month)->sum($amount);
                }
            }
            if (Schema::hasTable('expenses')) {
                $date = $this->column('expenses', ['expense_date', 'date', 'created_at']);
                $amount = $this->column('expenses', ['amount', 'total_amount']);
                if ($date && $amount) {
                    $result['expenses'] = (float) $this->monthQuery('expenses', $date, $month)->sum($amount);
                }
            }
            if (Schema::hasTable('fee_vouchers') && Schema::hasColumn('fee_vouchers', 'total_cents')) {
                [$year, $billingMonth] = explode('-', $month);
                $vouchers = \App\Models\FeeVoucher::with('payments')->whereNull('cancelled_at')->where('year', $year)->where('month', $billingMonth)->get();
                $billing = app(FeeBillingService::class);
                $result['billed'] = $vouchers->sum(fn ($v) => $v->total_cents + $billing->fine($v)) / 100;
                $result['pending'] = $vouchers->sum(fn ($v) => $billing->balance($v)) / 100;
                $result['collected'] = $this->monthQuery('fee_payments', 'payment_date', $month)->whereNull('reversed_at')->sum('amount_cents') / 100;
            }
            $result['percentage'] = $result['billed'] > 0 ? round($result['collected'] / $result['billed'] * 100, 1) : 0;
            $result['empty'] = $result['billed'] == 0 && $result['collected'] == 0;

            return $result;
        });
    }

    public function calendar(string $month): array
    {
        return $this->cached('calendar:'.$month, ['events' => [], 'empty' => true], function () use ($month) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth()->startOfWeek();
            $end = $start->addDays(41);
            $events = $this->eventQuery($start->toDateString(), $end->toDateString());

            return ['events' => $events, 'empty' => count($events) === 0];
        });
    }

    private function eventQuery(string $from, ?string $to = null, ?int $limit = null): array
    {
        $events = collect();
        if (Schema::hasTable('events')) {
            $q = DB::table('events')->whereRaw('COALESCE(end_date, date) >= ?', [$from])->when($to, fn ($q) => $q->whereDate('date', '<=', $to))->orderBy('date')->orderBy('id');
            if ($limit) {
                $q->limit($limit);
            }
            $events = $q->get()->map(fn ($e) => ['date' => substr($e->date, 0, 10), 'end_date' => substr($e->end_date ?? $e->date, 0, 10), 'title' => $e->title, 'detail' => $e->description ?? ucfirst($e->type), 'tone' => in_array($e->color, ['blue', 'green', 'coral', 'pink', 'amber']) ? $e->color : 'blue']);
        }
        if (Schema::hasTable('holidays')) {
            $q = DB::table('holidays')->whereDate('end_date', '>=', $from)->when($to, fn ($q) => $q->whereDate('start_date', '<=', $to))->orderBy('start_date')->orderBy('id');
            if ($limit) {
                $q->limit($limit);
            }
            $events = $events->concat($q->get()->map(fn ($e) => ['date' => substr($e->start_date, 0, 10), 'end_date' => substr($e->end_date, 0, 10), 'title' => $e->name, 'detail' => $e->description ?? 'Holiday', 'tone' => 'coral']));
        }
        $events = $events->sortBy('date');
        if ($limit) {
            $events = $events->take($limit);
        }

        return $events->values()->all();
    }

    public function upcomingEvents(): array
    {
        return $this->cached('upcoming', ['events' => [], 'empty' => true], function () {
            $events = $this->eventQuery(now()->toDateString(), null, 3);

            return ['events' => $events, 'empty' => count($events) === 0];
        });
    }

    public function activities(): array
    {
        return $this->cached('activities', ['items' => [], 'empty' => true], function () {
            if (! Schema::hasTable('activities')) {
                return ['items' => [], 'empty' => true];
            }
            $items = DB::table('activities')->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()->map(function ($a) {
                [$icon, $tone] = match ($a->type) {
                    'promotion' => ['classes', 'green'], 'admission' => ['users', 'green'], 'attendance' => ['calendar', 'coral'], 'fee', 'fees', 'fee_structure', 'discount', 'fee_setup' => ['wallet', 'green'], 'exam' => ['calendar', 'pink'], 'teacher' => ['teacher', 'blue'], 'library' => ['book', 'blue'], 'event', 'notice' => ['bell', 'pink'], default => ['users', 'blue']
                };
                $description = strtolower($a->description);
                $title = match (true) {
                    str_starts_with($description, 'student created:') => 'Student Added',
                    str_starts_with($description, 'teacher created:') => 'Teacher Added',
                    str_starts_with($description, 'exam created:') => 'Exam Created',
                    str_starts_with($description, 'fee payment received:') => 'Fee Payment',
                    str_starts_with($description, 'library book issued:') => 'Book Issued',
                    $a->type === 'attendance' => 'Attendance Updated',
                    default => ucfirst($a->type).' activity',
                };
                return ['icon' => $icon, 'tone' => $tone, 'title' => $title, 'detail' => $a->description, 'created_at' => $a->created_at];
            })->all();

            return ['items' => $items, 'empty' => count($items) === 0];
        });
    }

    public function link(string $route): ?string
    {
        return Route::has($route) ? route($route) : null;
    }
}
