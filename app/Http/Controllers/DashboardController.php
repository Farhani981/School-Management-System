<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardStatsService $stats): View
    {
        $today = now();
        $students = $stats->students();
        $teachers = $stats->teachers();
        $classes = $stats->classes();
        $fees = $stats->fees();
        $attendanceStats = $stats->attendance();
        $calendar = $stats->calendar($today->format('Y-m'));
        $upcoming = $stats->upcomingEvents();
        $activityFeed = $stats->activities();
        $feeTrend = collect(range(5, 0))->map(function ($offset) use ($stats, $today) {
            $month = $today->copy()->startOfMonth()->subMonths($offset);
            return ['label' => $month->format('M'), 'month' => $month->format('M Y'), 'amount' => $stats->fees($month->format('Y-m'))['collected']];
        });

        return view('dashboard.index', [
            'today' => $today,
            'feeTrend' => $feeTrend,
            'statistics' => [
                ['label' => 'Total Students', 'value' => number_format($students['total']), 'icon' => 'users', 'tone' => 'coral', 'note' => 'Boys '.$students['boys'].' / Girls '.$students['girls'], 'href' => $stats->link('students.index')],
                ['label' => 'Total Teachers', 'value' => number_format($teachers['total']), 'icon' => 'teacher', 'tone' => 'green', 'note' => $teachers['empty'] ? 'Not added yet' : $teachers['assigned'].' assigned / '.($teachers['total'] - $teachers['assigned']).' unassigned', 'href' => $stats->link('teachers.index')],
                ['label' => 'Total Classes/Sections', 'value' => number_format($classes['total']), 'icon' => 'classes', 'tone' => 'blue', 'note' => $classes['sections'].' sections', 'href' => $stats->link('academic-setup.index')],
                ['label' => 'Pending Fees', 'value' => 'Rs. '.number_format($fees['pending']), 'icon' => 'wallet', 'tone' => 'pink', 'note' => $fees['empty'] ? 'No fee data yet' : number_format($fees['percentage'], 1).'% collected this month', 'href' => $stats->link('fees.index')],
            ],
            'attendanceStats' => $attendanceStats, 'attendance' => $attendanceStats['rows'], 'fees' => $fees,
            'calendarEvents' => $calendar['events'], 'events' => $upcoming['events'],
            'activities' => collect($activityFeed['items'])->map(fn ($item) => $item + ['time' => Carbon::parse($item['created_at'])->diffForHumans()])->all(),
        ]);
    }

    public function calendar(Request $request, DashboardStatsService $stats)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);

        return response()->json($stats->calendar($data['month']) + ['upcoming' => $stats->upcomingEvents()['events']]);
    }
}
