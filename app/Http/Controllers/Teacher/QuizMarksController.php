<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SaveMarksRequest;
use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuizMarksController extends Controller
{
    public function __construct(
        protected QuizService $quizService
    ) {}

    public function edit(Quiz $quiz)
    {
        Gate::authorize('enterMarks', $quiz);

        $roster = $this->quizService->getRosterForQuiz($quiz);
        $stats = $this->quizService->calculateStatistics($quiz);
        $isFinalized = ($quiz->status === 'published' && $quiz->computedStatus() === 'completed');
        $audits = $quiz->audits()->with(['student', 'changedBy'])->take(20)->get();

        return view('teacher.quizzes.marks', [
            'quiz' => $quiz->load(['schoolClass', 'section', 'subject', 'session']),
            'roster' => $roster,
            'stats' => $stats,
            'isFinalized' => $isFinalized,
            'audits' => $audits,
        ]);
    }

    public function update(SaveMarksRequest $request, Quiz $quiz)
    {
        Gate::authorize('enterMarks', $quiz);

        $data = $request->validated();
        $isFinalize = ($data['action'] === 'finalize');
        $reason = $data['reason'] ?? null;

        $this->quizService->saveMarks(
            $quiz,
            $data['rows'],
            $request->user(),
            $reason,
            $isFinalize
        );

        if ($isFinalize) {
            return redirect()->route('teacher.quizzes.show', $quiz)
                ->with('success', "All marks finalized successfully for '{$quiz->title}'. Assessment is now complete.");
        }

        return redirect()->route('teacher.quizzes.marks', $quiz)
            ->with('success', "Draft marks saved successfully. You can return and continue anytime.");
    }

    public function import(Request $request, Quiz $quiz)
    {
        Gate::authorize('enterMarks', $quiz);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $content = file_get_contents($request->file('csv_file')->getRealPath());
        $result = $this->quizService->parseCsvImport($quiz, $content);

        return response()->json($result);
    }
}
