<?php

namespace App\Http\Controllers;

use App\Http\Requests\HolidayRequest;
use App\Models\Holiday;
use Illuminate\Support\Facades\Gate;

class HolidayController extends Controller
{
    public function index()
    {
        Gate::authorize('attendance.staff');

        return view('attendance.holidays', ['holidays' => Holiday::orderByDesc('start_date')->paginate(15)]);
    }

    public function store(HolidayRequest $request)
    {
        Gate::authorize('attendance.staff');
        Holiday::create($request->validated());

        return back()->with('success', 'Holiday added.');
    }

    public function update(HolidayRequest $request, Holiday $holiday)
    {
        Gate::authorize('attendance.staff');
        $holiday->update($request->validated());

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday)
    {
        Gate::authorize('attendance.staff');
        $holiday->delete();

        return back()->with('success', 'Holiday deleted.');
    }
}
