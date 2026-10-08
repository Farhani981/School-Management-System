<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeeStructureCopyRequest;
use App\Http\Requests\FeeStructureRequest;
use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Services\FeeSetupService;
use Illuminate\Http\Request;

class FeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['academic_session_id' => 'nullable|integer|exists:academic_sessions,id', 'school_class_id' => 'nullable|integer|exists:school_classes,id']);
        $session = $data['academic_session_id'] ?? AcademicSession::where('is_current', true)->value('id');
        $class = $data['school_class_id'] ?? null;
        $structures = $session && $class ? FeeStructure::with('feeType')->where('academic_session_id', $session)->where('school_class_id', $class)->get()->keyBy('fee_type_id') : collect();
        $totals = FeeStructure::where('academic_session_id', $session)->whereHas('feeType', fn ($q) => $q->active()->where('frequency', 'monthly'))->select('school_class_id')->selectRaw('SUM(amount) AS total')->groupBy('school_class_id')->pluck('total', 'school_class_id');

        return view('fees.structures', ['sessions' => AcademicSession::orderByDesc('name')->get(), 'classes' => SchoolClass::orderBy('id')->get()->sortBy(fn ($c) => preg_match('/(?:Class|Grade)\s+(\d+)/i', $c->name, $m) ? 10 + (int) $m[1] : 0), 'types' => FeeType::active()->orderBy('name')->get(), 'structures' => $structures, 'session' => $session, 'class' => $class, 'totals' => $totals]);
    }

    public function store(FeeStructureRequest $request, FeeSetupService $service)
    {
        $service->saveStructure($request->validated());

        return back()->with('success', 'Fee structure saved.');
    }

    public function copy(FeeStructureCopyRequest $request, FeeSetupService $service)
    {
        $service->copyStructure($request->validated());

        return back()->with('success', 'Fee structure copied.');
    }
}
