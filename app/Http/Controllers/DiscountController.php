<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignStudentDiscountRequest;
use App\Http\Requests\DiscountRequest;
use App\Models\Discount;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Services\FeeSetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100']);

        return view('fees.discounts.index', ['discounts' => Discount::when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))->orderBy('name')->paginate(15)->withQueryString(), 'activeDiscounts' => Discount::where('is_active', true)->orderBy('name')->get(), 'types' => FeeType::active()->orderBy('name')->get(), 'assignments' => StudentDiscount::with(['student', 'discount', 'feeType'])->latest('id')->paginate(15, ['*'], 'assignments_page')->withQueryString(), 'selectedStudent' => old('student_id') ? Student::find(old('student_id')) : null]);
    }

    public function create()
    {
        return view('fees.discounts.form', ['discount' => new Discount]);
    }

    public function store(DiscountRequest $request)
    {
        DB::transaction(fn () => Discount::create($request->validated()));

        return redirect()->route('fees.setup.discounts.index')->with('success', 'Discount added.');
    }

    public function edit(Discount $discount)
    {
        return view('fees.discounts.form', compact('discount'));
    }

    public function update(DiscountRequest $request, Discount $discount)
    {
        DB::transaction(fn () => Discount::whereKey($discount->id)->lockForUpdate()->firstOrFail()->update($request->validated()));

        return redirect()->route('fees.setup.discounts.index')->with('success', 'Discount updated.');
    }

    public function destroy(Discount $discount)
    {
        $deleted = DB::transaction(function () use ($discount) {
            $discount = Discount::whereKey($discount->id)->lockForUpdate()->firstOrFail();
            if ($discount->assignments()->exists()) {
                return false;
            }
            $discount->delete();

            return true;
        });

        return back()->with($deleted ? 'success' : 'error', $deleted ? 'Discount deleted.' : 'This discount is assigned to students. Remove its assignments or deactivate it.');
    }

    public function assign(AssignStudentDiscountRequest $request, FeeSetupService $service)
    {
        $service->assignDiscount($request->validated());

        return back()->with('success', 'Student discount assigned.');
    }

    public function unassign(StudentDiscount $assignment)
    {
        DB::transaction(function () use ($assignment) {
            $assignment->delete();
            FeeSetupService::log('Student discount removed from student #'.$assignment->student_id.'.', 'discount', 'students:'.$assignment->student_id);
        });

        return back()->with('success', 'Student discount removed.');
    }
}
