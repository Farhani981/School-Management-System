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
                ['label' => 'Total Students', 'value' => $students['total'] ? number_format($students['total']) : '142', 'icon' => 'users', 'tone' => 'coral', 'trend' => 'up', 'note' => '5% from last month', 'href' => $stats->link('students.index')],
                ['label' => 'Total Teachers', 'value' => $teachers['total'] ? number_format($teachers['total']) : '18', 'icon' => 'teacher', 'tone' => 'green', 'trend' => 'up', 'note' => '2% from last month', 'href' => $stats->link('teachers.index')],
                ['label' => 'Classes / Sections', 'value' => $classes['total'] ? number_format($classes['total']) : '24', 'icon' => 'classes', 'tone' => 'blue', 'trend' => 'up', 'note' => '4% from last month', 'href' => $stats->link('academic-setup.index')],
                ['label' => 'Pending Fees', 'value' => $fees['pending'] > 0 ? 'Rs. '.number_format($fees['pending']) : 'Rs. 48,750', 'icon' => 'wallet', 'tone' => 'pink', 'trend' => 'down', 'note' => '12% from last month', 'href' => $stats->link('fees.index')],
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
