<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunPromotionRequest;
use App\Models\Promotion;
use App\Services\PromotionWorkflow;
use App\Services\StudentDirectory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request, StudentDirectory $directory)
    {
        $filters = $directory->filters($request);
        $filters['status'] = 'Active';
        return view('promotion.index', ['students' => $directory->query($filters)->paginate(50)->withQueryString(), 'options' => StudentRecordsController::options(), 'filters' => $filters]);
    }

    public function store(RunPromotionRequest $request, PromotionWorkflow $workflow)
    {
        $workflow->run($request->validated(), $request->user()?->id);
        return redirect()->route('promotion.history')->with('success', 'Selected students processed successfully.');
    }

    public function history(Request $request)
    {
        $filters = $request->validate(['action' => ['nullable', Rule::in(array_keys(Promotion::ACTIONS))], 'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id']]);
        $query = Promotion::with('student');
        if (!empty($filters['action'])) $query->where('action', $filters['action']);
        if (!empty($filters['session_id'])) $query->where('from_session_id', $filters['session_id']);
        return view('promotion.history', ['promotions' => $query->latest('id')->paginate(25)->withQueryString(), 'options' => StudentRecordsController::options(), 'filters' => $filters]);
    }
}
