<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\FeeCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeSetupController extends Controller
{
    public function index()
    {
        return redirect()->route('fees.setup.types.index');
    }

    public function comingSoon(string $module)
    {
        return view('fees.coming-soon', compact('module'));
    }

    public function students(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:100']);
        $fullName = DB::getDriverName() === 'mysql' ? "CONCAT(first_name, ' ', COALESCE(last_name, ''))" : "first_name || ' ' || COALESCE(last_name, '')";

        return response()->json(Student::where(fn ($q) => $q->where('first_name', 'like', '%'.$data['q'].'%')->orWhere('last_name', 'like', '%'.$data['q'].'%')->orWhere('admission_no', 'like', '%'.$data['q'].'%')->orWhere('phone', 'like', '%'.$data['q'].'%')->orWhereRaw($fullName.' LIKE ?', ['%'.$data['q'].'%']))->orderBy('first_name')->limit(20)->get(['id', 'first_name', 'last_name', 'admission_no'])->map(fn ($s) => ['id' => $s->id, 'label' => trim($s->first_name.' '.$s->last_name).' / '.$s->admission_no]));
    }

    public function preview(Request $request, Student $student, FeeCalculatorService $calculator)
    {
        $data = $request->validate(['month' => 'required|integer|min:1|max:12', 'year' => 'required|integer|min:1900|max:9999']);

        return view('fees.preview', ['student' => $student, 'calculation' => $calculator->calculateForStudent($student, (int) $data['month'], (int) $data['year']), 'month' => $data['month'], 'year' => $data['year']]);
    }
}
