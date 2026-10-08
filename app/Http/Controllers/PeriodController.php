<?php

namespace App\Http\Controllers;

use App\Models\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PeriodController extends Controller
{
    public function index()
    {
        $periods = Period::ordered()->get();
        $nextSortOrder = ($periods->max('sort_order') ?? 0) + 1;

        return view('timetable.periods', [
            'periods' => $periods,
            'nextSortOrder' => $nextSortOrder,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_break' => 'nullable|boolean',
            'sort_order' => 'required|integer|min:1|max:10000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_break'] = $request->boolean('is_break');
        $validated['is_active'] = $request->boolean('is_active', true);

        $this->savePeriod($validated);
        \App\Services\DashboardStatsService::invalidate();

        return redirect()->route('timetable.periods.index')->with('success', 'Period created successfully.');
    }

    public function update(Request $request, Period $period)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_break' => 'nullable|boolean',
            'sort_order' => 'required|integer|min:1|max:10000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_break'] = $request->boolean('is_break');
        $validated['is_active'] = $request->boolean('is_active');

        $this->savePeriod($validated, $period);
        \App\Services\DashboardStatsService::invalidate();

        return redirect()->route('timetable.periods.index')->with('success', 'Period updated successfully.');
    }

    public function destroy(Period $period)
    {
        if ($period->timetableEntries()->exists()) {
            return back()->with('error', 'Cannot delete this period because it has allocated timetable entries.');
        }

        $period->delete();
        \App\Services\DashboardStatsService::invalidate();

        return redirect()->route('timetable.periods.index')->with('success', 'Period deleted successfully.');
    }

    private function savePeriod(array $data, ?Period $period = null): void
    {
        DB::transaction(function () use ($data, $period) {
            $periods = Period::ordered()->lockForUpdate()->get();
            if ($data['is_active'] && Period::hasOverlap($data['start_time'], $data['end_time'], $period?->id)) {
                throw ValidationException::withMessages(['start_time' => 'This time slot overlaps with another active period. Choose a free time or edit the neighbouring period timings first.']);
            }
            $target = (int) $data['sort_order'];
            if (!$period) {
                foreach ($periods->where('sort_order', '>=', $target)->sortByDesc('sort_order') as $following) {
                    $following->update(['sort_order' => $following->sort_order + 1]);
                }
                Period::create($data);
                return;
            }
            $period = $periods->firstWhere('id', $period->id);
            abort_unless($period, 404);
            $previous = $period->sort_order;
            if ($previous !== $target) {
                // Free the original order before shifting so the unique index stays valid.
                $period->update(['sort_order' => max($periods->max('sort_order'), $target) + 1]);
                $others = $periods->where('id', '!=', $period->id);
                if ($target < $previous) {
                    foreach ($others->whereBetween('sort_order', [$target, $previous - 1])->sortByDesc('sort_order') as $following) {
                        $following->update(['sort_order' => $following->sort_order + 1]);
                    }
                } else {
                    foreach ($others->whereBetween('sort_order', [$previous + 1, $target])->sortBy('sort_order') as $following) {
                        $following->update(['sort_order' => $following->sort_order - 1]);
                    }
                }
            }
            $period->update($data);
        });
    }
}
