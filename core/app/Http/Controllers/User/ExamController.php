<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\AttendExam;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\GetCertificateUser;
use App\Models\Question;
use App\Support\ExamScoreCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExamController extends Controller {
    public function start($slug) {
        $exam = Exam::active()->running()->where('slug', $slug)->firstOrFail();
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $questionCount = count($allQuestionIds);
        abort_if($questionCount < 1, 404, 'This exam has no active questions.');

        $user = auth()->user();

        if (!$user->plan_id) {
            $notify[] = ['error', 'You do not have any active subscription plan.'];
            return back()->withNotify($notify);
        }

        if ($user->expired_date < now()->format('Y-m-d')) {
            $notify[] = ['error', 'Your subscription plan has expired. Please renew to continue using the service.'];
            return back()->withNotify($notify);

        }

        if ($user->exam_limit <= 0) {
            $notify[] = ['error', 'You do not have enough credit to perform this action.'];
            return back()->withNotify($notify);
        }

        // Every entry from All Exams is a fresh attempt and must pass through settings.
        $pageTitle = 'Exam Settings';
        $examSettings = array_merge($this->defaultExamSettings(), (array) ($user->exam_preferences ?? []));
        return view('Template::user.exams.configure', compact('pageTitle', 'exam', 'questionCount', 'examSettings'));
    }

    public function resume($id) {
        $attendExam = AttendExam::where('user_id', auth()->id())
            ->where('is_submit', Status::NO)
            ->findOrFail($id);
        $exam = Exam::active()->running()->whereKey($attendExam->exam_id)->firstOrFail();

        return $this->renderAttempt($exam, $attendExam, 'Resume Exam');
    }

    public function take($id) {
        $attendExam = AttendExam::where('user_id', auth()->id())
            ->where('is_submit', Status::NO)
            ->findOrFail($id);
        $exam = Exam::active()->running()->whereKey($attendExam->exam_id)->firstOrFail();

        return $this->renderAttempt($exam, $attendExam, 'Start Exam');
    }

    private function renderAttempt(Exam $exam, AttendExam $attendExam, string $pageTitle) {
        $allQuestionIds = $this->orderedQuestionIds($exam);
        // Keep older in-progress attempts resumable with their original admin duration.
        if (!$attendExam->exam_settings) {
            $legacySettings = $this->defaultExamSettings();
            $legacySettings['timer'] = 'legacy_duration';
            $attendExam->exam_settings = $legacySettings;
            if ($attendExam->remaining_seconds === null) {
                $attendExam->remaining_seconds = (int) $exam->duration * 60;
            }
            $attendExam->end_time = null;
            $attendExam->save();
        }

        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attendExam);
        $questionCount = count($flowQuestionIds);
        abort_if($questionCount < 1, 404, 'This exam has no active questions.');

        $settings = $attendExam->exam_settings
            ? $this->settingsForAttempt($attendExam)
            : array_merge($this->defaultExamSettings(), ['timer' => 'legacy_duration']);
        $showAnswers = (bool) ($settings['include_show_answer'] ?? true);
        $showLiveScore = (bool) ($settings['show_score_live'] ?? true);
        $timerLabel = $this->timerLabel($settings, $questionCount);
        $liveScore = $this->liveScoreForAttempt($exam, $attendExam, $allQuestionIds, $flowQuestionIds, true);
        $sessionIncorrect = [];
        foreach ($flowQuestionIds as $flowIndex => $questionId) {
            $result = $liveScore['question_results'][$questionId] ?? [];
            if (!empty($result['answered']) && empty($result['is_correct'])) {
                $sessionIncorrect[] = $flowIndex;
            }
        }

        $sessionAnswers    = $attendExam->session_answers ?? [];
        $sessionAnswerData = $attendExam->session_answer_data ?? [];
        $sessionIndex      = max(0, min($questionCount - 1, (int) ($attendExam->current_question ?? 0)));
        $sessionRemaining  = $attendExam->remaining_seconds;
        $sessionPaused = (bool) ($attendExam->is_paused ?? false);
        $sessionFlagged    = $this->globalIndicesToFlowIndices((array) ($attendExam->flagged_questions ?? []), $flowQuestionIds, $allQuestionIds);
        $sessionRevealed   = $this->globalIndicesToFlowIndices((array) ($attendExam->revealed_questions ?? []), $flowQuestionIds, $allQuestionIds);
        $sessionCommitted  = $this->globalIndicesToFlowIndices((array) ($attendExam->committed_questions ?? []), $flowQuestionIds, $allQuestionIds);
        $answeredIndexes = [];
        foreach ($flowQuestionIds as $index => $questionId) {
            $hasSavedAnswer = array_key_exists($questionId, $sessionAnswers);
            $hasSavedDragAnswer = !empty($sessionAnswerData[$questionId] ?? []);
            if ($hasSavedAnswer || $hasSavedDragAnswer) {
                $answeredIndexes[] = $index;
            }
        }
        $question          = $this->loadQuestionForAttempt($exam, $flowQuestionIds[$sessionIndex], $attendExam);
        $savedAnswer       = $sessionAnswers[(string) $question->id] ?? $sessionAnswers[$question->id] ?? null;
        $savedDragData     = $sessionAnswerData[(string) $question->id] ?? $sessionAnswerData[$question->id] ?? [];
        $currentGlobalIndex = array_search((string) $question->id, $allQuestionIds, true);
        $isQuestionCommitted = $currentGlobalIndex !== false
            && in_array((int) $currentGlobalIndex, array_map('intval', (array) ($attendExam->committed_questions ?? [])), true);
        $dragFeedback = $isQuestionCommitted && $question->question_type === 'drag_drop'
            ? $this->dragDropFeedbackForQuestion($question, $savedDragData)
            : null;

        return view('Template::user.exams.start', compact(
            'pageTitle', 'exam', 'attendExam', 'question', 'questionCount', 'savedAnswer', 'savedDragData',
            'sessionIndex', 'sessionRemaining', 'sessionPaused', 'sessionFlagged', 'sessionRevealed', 'answeredIndexes',
            'sessionCommitted', 'sessionIncorrect', 'settings', 'showAnswers', 'showLiveScore', 'timerLabel', 'liveScore', 'dragFeedback'
        ));
    }

    private function activeAttempt($id): AttendExam {
        return AttendExam::where('user_id', auth()->id())
            ->where('is_submit', Status::NO)
            ->findOrFail($id);
    }

    private function dragDropFeedbackForQuestion(Question $question, array $submitted): array {
        $data = ExamScoreCalculator::decodeAnswerData($question->answer_data ?? []);
        $slotResults = ExamScoreCalculator::dragDropSlotResults($data, $submitted);

        return [
            'is_correct' => count($slotResults) > 0
                && collect($slotResults)->every(fn ($result) => ($result['status'] ?? null) === 'correct'),
            'correct_matches' => (array) ($data['correctMatches'] ?? []),
            'slot_results' => $slotResults,
        ];
    }

    public function begin(Request $request, $slug) {
        $exam = Exam::active()->running()->where('slug', $slug)->firstOrFail();
        $allQuestionIds = $this->orderedQuestionIds($exam);
        abort_if(!$allQuestionIds, 404, 'This exam has no active questions.');

        $user = auth()->user();
        if (!$user->plan_id || $user->expired_date < now()->format('Y-m-d')) {
            $notify[] = ['error', 'Your active subscription is required to start this exam.'];
            return back()->withNotify($notify);
        }

        if ($user->exam_limit <= 0) {
            $notify[] = ['error', 'You do not have enough credit to perform this action.'];
            return back()->withNotify($notify);
        }

        $validated = $request->validate([
            'question_order' => 'required|in:original,random',
            'randomize_options' => 'nullable|boolean',
            'show_score_live' => 'nullable|boolean',
            'include_show_answer' => 'nullable|boolean',
            'inline_feedback' => 'nullable|boolean',
            'timer' => 'required|in:not_timed,ccna_120,90_mins,60_mins,30s_per_q,60s_per_q,90s_per_q',
        ]);

        $settings = [
            'question_order' => $validated['question_order'],
            'randomize_options' => $request->boolean('randomize_options'),
            'show_score_live' => $request->boolean('show_score_live'),
            'include_show_answer' => $request->boolean('include_show_answer'),
            'inline_feedback' => $request->boolean('inline_feedback'),
            'timer' => $validated['timer'],
        ];
        $orderedIds = $settings['question_order'] === 'random'
            ? collect($allQuestionIds)->shuffle()->values()->all()
            : $allQuestionIds;
        $questionOptions = Question::whereIn('id', $allQuestionIds)
            ->with(['options' => fn ($query) => $query->active()->orderBy('id')])
            ->get()->keyBy(fn ($question) => (string) $question->id);
        $optionOrders = [];
        foreach ($allQuestionIds as $questionId) {
            $optionIds = $questionOptions->get($questionId)?->options->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
            $optionOrders[$questionId] = $settings['randomize_options']
                ? collect($optionIds)->shuffle()->values()->all()
                : $optionIds;
        }

        $seconds = $this->timerSeconds($settings['timer'], count($orderedIds));
        $attemptId = DB::transaction(function () use ($user, $exam, $settings, $orderedIds, $optionOrders, $seconds) {
            $lockedUser = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            abort_if($lockedUser->exam_limit <= 0, 422, 'You do not have enough credit to perform this action.');

            $attempt = new AttendExam();
            $attempt->user_id = $lockedUser->id;
            $attempt->exam_id = $exam->id;
            $attempt->start_time = now();
            $attempt->end_time = null;
            $attempt->remaining_seconds = $seconds;
            $attempt->current_question = 0;
            $attempt->exam_settings = $settings;
            $attempt->question_order = $orderedIds;
            $attempt->option_orders = $optionOrders;
            $attempt->save();

            $lockedUser->exam_limit -= 1;
            $lockedUser->exam_preferences = $settings;
            $lockedUser->save();
            return $attempt->id;
        });

        return to_route('user.exam.take', $attemptId);
    }

    public function revealAnswer(Request $request, $id, $index) {
        $attempt = $this->activeAttempt($id);
        $exam = Exam::active()->running()->whereKey($attempt->exam_id)->firstOrFail();
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $index = (int) $index;
        abort_if($index < 0 || $index >= count($flowIds), 404);

        $questionId = $flowIds[$index];
        $question = $this->loadQuestionForAttempt($exam, $questionId, $attempt);
        $answer = $request->input('answer');
        $answerData = $request->input('answer_data', []);
        $answers = (array) ($attempt->session_answers ?? []);
        $answerDataByQuestion = (array) ($attempt->session_answer_data ?? []);

        if ($question->question_type === 'drag_drop') {
            $decoded = is_string($answerData) ? json_decode($answerData, true) : $answerData;
            $decoded = is_array($decoded) ? $decoded : [];
            $answerDataByQuestion[$questionId] = $decoded;
            if ($decoded) {
                $answers[$questionId] = 1;
            } else {
                unset($answers[$questionId], $answerDataByQuestion[$questionId]);
            }
        } else {
            $values = is_array($answer) ? array_values(array_unique(array_map('intval', $answer))) : (($answer === null || $answer === '') ? [] : [(int) $answer]);
            $validIds = $question->options->pluck('id')->map(fn ($id) => (int) $id)->all();
            abort_if(array_diff($values, $validIds), 422, 'Invalid answer option.');
            abort_if(
                $question->question_type === 'multiple_selection'
                    && count($values) > ExamScoreCalculator::requiredSelectionCount($question),
                422,
                'Too many options selected.'
            );
            if ($values) {
                $answers[$questionId] = $question->question_type === 'multiple_selection' ? $values : $values[0];
            } else {
                unset($answers[$questionId]);
            }
        }

        $globalIndex = array_search($questionId, $allQuestionIds, true);
        $committed = array_map('intval', (array) ($attempt->committed_questions ?? []));
        $revealed = array_map('intval', (array) ($attempt->revealed_questions ?? []));
        if ($globalIndex !== false) {
            $committed[] = (int) $globalIndex;
            $revealed[] = (int) $globalIndex;
        }
        $attempt->session_answers = $answers;
        $attempt->session_answer_data = $answerDataByQuestion;
        $attempt->committed_questions = array_values(array_unique($committed));
        $attempt->revealed_questions = array_values(array_unique($revealed));
        $attempt->current_question = $index;
        $attempt->save();

        $questionScore = ExamScoreCalculator::evaluate(
            collect([$question]),
            [$questionId => $answers[$questionId] ?? null],
            [$questionId => $answerDataByQuestion[$questionId] ?? []],
            [0],
            (float) $exam->pass_percentage
        )['question_results'][$questionId] ?? [];
        $liveScore = $this->liveScoreForAttempt($exam, $attempt, $allQuestionIds, $flowIds);

        return response()->json([
            'success' => true,
            'correct_option_ids' => $questionScore['correct_option_ids'] ?? [],
            'is_correct' => (bool) ($questionScore['is_correct'] ?? false),
            'correct_matches' => $questionScore['correct_matches'] ?? [],
            'explanation' => $question->explanationForDisplayedOptionOrder() ?: '<p>' . __('No explanation has been added yet.') . '</p>',
            'live_score' => $liveScore,
        ]);
    }

    public function confirmDragDrop(Request $request, $id, $index) {
        $attempt = $this->activeAttempt($id);
        $exam = Exam::active()->running()->whereKey($attempt->exam_id)->firstOrFail();
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $index = (int) $index;
        abort_if($index < 0 || $index >= count($flowIds), 404);

        $questionId = (string) $flowIds[$index];
        $question = $this->loadQuestionForAttempt($exam, $questionId, $attempt);
        abort_unless($question->question_type === 'drag_drop', 422, 'This is not a drag-and-drop question.');

        $data = ExamScoreCalculator::decodeAnswerData($question->answer_data ?? []);
        $targets = array_map('strval', array_values((array) ($data['targets'] ?? [])));
        $items = array_map('strval', array_values((array) ($data['items'] ?? [])));
        $submitted = ExamScoreCalculator::decodeAnswerData($request->input('answer_data', []));
        abort_if(!$targets || !$items || !$submitted, 422, 'Place at least one item before confirming.');
        abort_if(count($submitted) > count($targets), 422, 'Too many target matches were submitted.');

        $availableItemCounts = array_count_values($items);
        $usedItemCounts = [];
        $normalizedMatches = [];
        foreach ($submitted as $target => $item) {
            $target = (string) $target;
            abort_unless(in_array($target, $targets, true), 422, 'An invalid target was submitted.');
            abort_unless(is_scalar($item), 422, 'An invalid item was submitted.');
            $item = (string) $item;
            abort_unless(in_array($item, $items, true), 422, 'An invalid item was submitted.');

            $usedItemCounts[$item] = ($usedItemCounts[$item] ?? 0) + 1;
            abort_if($usedItemCounts[$item] > ($availableItemCounts[$item] ?? 0), 422, 'An item cannot be assigned more than once.');
            $normalizedMatches[$target] = $item;
        }

        $globalIndex = array_search($questionId, array_map('strval', $allQuestionIds), true);
        abort_if($globalIndex === false, 404);
        $committed = array_map('intval', (array) ($attempt->committed_questions ?? []));
        $answers = (array) ($attempt->session_answers ?? []);
        $answerDataByQuestion = (array) ($attempt->session_answer_data ?? []);

        if (in_array((int) $globalIndex, $committed, true)) {
            $existingMatches = (array) ($answerDataByQuestion[$questionId] ?? []);
            ksort($existingMatches);
            $submittedForCompare = $normalizedMatches;
            ksort($submittedForCompare);
            abort_unless($existingMatches === $submittedForCompare, 409, 'This answer has already been confirmed.');
        } else {
            $answerDataByQuestion[$questionId] = $normalizedMatches;
            $answers[$questionId] = 1;
            $committed[] = (int) $globalIndex;

            $attempt->session_answers = $answers;
            $attempt->session_answer_data = $answerDataByQuestion;
            $attempt->committed_questions = array_values(array_unique($committed));
            $attempt->current_question = $index;
            $attempt->save();
        }

        $feedback = $this->dragDropFeedbackForQuestion($question, (array) ($answerDataByQuestion[$questionId] ?? $normalizedMatches));

        return response()->json([
            'success' => true,
            'is_correct' => $feedback['is_correct'],
            'correct_matches' => $feedback['correct_matches'],
            'slot_results' => $feedback['slot_results'],
        ]);
    }

    public function question($id, $index) {
        $attendExam = $this->activeAttempt($id);
        $exam = Exam::active()->running()->whereKey($attendExam->exam_id)->firstOrFail();
        $index = (int) $index;

        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attendExam);
        abort_if($index < 0 || $index >= count($flowQuestionIds), 404);

        $question = $this->loadQuestionForAttempt($exam, $flowQuestionIds[$index], $attendExam);
        $sessionAnswers = $attendExam->session_answers ?? [];
        $sessionAnswerData = $attendExam->session_answer_data ?? [];
        $savedAnswer = $sessionAnswers[(string) $question->id] ?? $sessionAnswers[$question->id] ?? null;
        $savedDragData = $sessionAnswerData[(string) $question->id] ?? $sessionAnswerData[$question->id] ?? [];
        $questionIndex = $index;
        $globalIndex = array_search((string) $question->id, $allQuestionIds, true);
        $revealed = $globalIndex !== false && in_array((int) $globalIndex, array_map('intval', (array) ($attendExam->revealed_questions ?? [])), true);
        $committed = $globalIndex !== false && in_array((int) $globalIndex, array_map('intval', (array) ($attendExam->committed_questions ?? [])), true);
        $dragFeedback = $committed && $question->question_type === 'drag_drop'
            ? $this->dragDropFeedbackForQuestion($question, (array) $savedDragData)
            : null;

        $settings = $attendExam->exam_settings
            ? $this->settingsForAttempt($attendExam)
            : array_merge($this->defaultExamSettings(), ['timer' => 'legacy_duration']);
        $showAnswers = (bool) ($settings['include_show_answer'] ?? true);

        return response()->json([
            'success' => true,
            'index' => $index,
            'question_id' => $question->id,
            'answered' => $savedAnswer !== null || !blank($savedDragData),
            'html' => view('Template::user.exams.partials.question', compact(
                'exam', 'question', 'questionIndex', 'savedAnswer', 'savedDragData', 'revealed', 'showAnswers', 'dragFeedback'
            ))->render(),
            'revealed' => $revealed,
            'committed' => $committed,
        ]);
    }

    public function saveSession(Request $request, $id) {
        $attendExam = $this->activeAttempt($id);
        $exam = Exam::active()->running()->whereKey($attendExam->exam_id)->firstOrFail();

        $allQuestionIds = $this->orderedQuestionIds($exam);
        $questionIds = $this->flowQuestionIds($allQuestionIds, $attendExam);
        $cleanAnswers = (array) ($attendExam->session_answers ?? []);
        $cleanAnswerData = (array) ($attendExam->session_answer_data ?? []);
        $questionId = $request->input('question_id');
        if ($questionId !== null) {
            $questionId = (string) $questionId;
            abort_unless(in_array($questionId, $questionIds, true), 422, 'Invalid question.');
            $answer = $request->input('answer');
            $hasAnswer = is_array($answer) ? count($answer) > 0 : ($answer !== null && $answer !== '');
            if ($hasAnswer) {
                $cleanAnswers[$questionId] = is_array($answer) ? array_values(array_map('intval', $answer)) : (int) $answer;
            } else {
                unset($cleanAnswers[$questionId]);
            }

            $answerData = $request->input('answer_data');
            if (is_array($answerData) && count($answerData)) {
                $cleanAnswerData[$questionId] = $answerData;
            } else {
                unset($cleanAnswerData[$questionId]);
            }
        }

        $remaining = $request->input('remaining_seconds');
        $lastQuestionIndex = max(0, count($questionIds) - 1);
        $attendExam->current_question   = max(0, min($lastQuestionIndex, (int) $request->input('current_question', 0)));
        $attendExam->remaining_seconds  = $remaining === null ? null : max(0, (int) $remaining);
        if ($request->has('is_paused')) {
            $attendExam->is_paused = $request->boolean('is_paused');
        }
        $attendExam->session_answers    = $cleanAnswers;
        $attendExam->session_answer_data= $cleanAnswerData;
        $attendExam->flagged_questions = $this->mergeFlowIndices(
            (array) ($attendExam->flagged_questions ?? []),
            (array) $request->input('flagged_questions', []),
            $questionIds,
            $allQuestionIds
        );
        $attendExam->revealed_questions = $this->mergeFlowIndices(
            (array) ($attendExam->revealed_questions ?? []),
            (array) $request->input('revealed_questions', []),
            $questionIds,
            $allQuestionIds
        );
        $attendExam->committed_questions = $this->mergeFlowIndices(
            (array) ($attendExam->committed_questions ?? []),
            (array) $request->input('committed_questions', []),
            $questionIds,
            $allQuestionIds
        );
        $attendExam->updated_at         = now();
        $attendExam->save();

        return response()->json([
            'success' => true,
            'remaining_seconds' => $attendExam->remaining_seconds,
            'live_score' => $this->liveScoreForAttempt($exam, $attendExam, $allQuestionIds, $questionIds),
            'saved_at' => $attendExam->updated_at?->toIso8601String(),
        ]);
    }

    public function submit(Request $request, $id) {
        $submitExam = $this->activeAttempt($id);
        $exam = Exam::active()->running()->whereKey($submitExam->exam_id)->withWhereHas('questions', function ($query) {
            $query->active()->select([
                'id', 'exam_id', 'title', 'question_type', 'answer_data', 'result_option_id'
            ])->orderBy('sort_order')->orderBy('id')->with('options', function ($q) {
                $q->active()->select(['id', 'question_id', 'title']);
            });
        })->firstOrFail();

        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $submitExam);
        $flowQuestionIdSet = array_fill_keys($flowQuestionIds, true);
        $questionsById = $exam->questions->keyBy(fn ($question) => (string) $question->id);
        $flowQuestions = collect($flowQuestionIds)
            ->map(fn ($questionId) => $questionsById->get($questionId))
            ->filter()
            ->values();

        $storedAnswers = array_intersect_key((array) ($submitExam->session_answers ?? []), $flowQuestionIdSet);
        foreach ((array) $request->input('answer', []) as $questionId => $answer) {
            $questionId = (string) $questionId;
            if (!isset($flowQuestionIdSet[$questionId])) {
                continue;
            }
            $hasAnswer = is_array($answer) ? count($answer) > 0 : ($answer !== null && $answer !== '');
            if ($hasAnswer) {
                $storedAnswers[$questionId] = $answer;
            }
        }
        $storedAnswerData = array_intersect_key((array) ($submitExam->session_answer_data ?? []), $flowQuestionIdSet);
        foreach ((array) $request->input('answer_data', []) as $questionId => $answerData) {
            $questionId = (string) $questionId;
            if (!isset($flowQuestionIdSet[$questionId])) {
                continue;
            }
            $decodedAnswerData = ExamScoreCalculator::decodeAnswerData($answerData);
            if ($decodedAnswerData) {
                $storedAnswerData[$questionId] = $decodedAnswerData;
            } else {
                unset($storedAnswerData[$questionId]);
            }
        }
        foreach ($storedAnswerData as $questionId => $answerData) {
            $question = $questionsById->get((string) $questionId);
            $decodedAnswerData = ExamScoreCalculator::decodeAnswerData($answerData);
            if ($question?->question_type === 'drag_drop' && $decodedAnswerData) {
                $storedAnswers[(string) $questionId] = 1;
            } else {
                $storedAnswerData[(string) $questionId] = $decodedAnswerData;
            }
        }
        $storedAnswers = array_intersect_key($storedAnswers, $flowQuestionIdSet);
        $request->merge(['answer' => $storedAnswers, 'answer_data' => $storedAnswerData]);

        $request->validate([
            'answer'   => 'nullable|array',
            'answer.*' => 'nullable',
            'answer_data' => 'nullable|array',
            'answer_data.*' => 'nullable',
        ], [
            'answer.array'      => 'Invalid answer format.',
        ]);

        foreach ($request->input('answer', []) as $questionId => $answer) {
            $question = $questionsById->get((string) $questionId);
            if (!$question) {
                $notify[] = ['error', 'Invalid question submitted.'];
                return back()->withNotify($notify);
            }

            if ($question->question_type === 'drag_drop') {
                $rawSubmitted = $request->input("answer_data.$questionId", []);
                $submitted = is_string($rawSubmitted) ? json_decode($rawSubmitted, true) : $rawSubmitted;
                if (!is_array($submitted)) {
                    return back()->withErrors(['answer' => 'Invalid drag and drop answer.'])->withInput();
                }
                continue;
            }

            $optionIds = is_array($answer) ? $answer : [$answer];
            $valid = $question->options()->whereIn('id', $optionIds)->count() === count(array_unique($optionIds));
            $tooManySelections = $question->question_type === 'multiple_selection'
                && count(array_unique(array_map('intval', $optionIds))) > ExamScoreCalculator::requiredSelectionCount($question);
            if (!$valid || $tooManySelections || ($question->question_type === 'multiple_selection' && count($optionIds) < 1)) {
                return back()->withErrors(['answer' => 'One or more selected options are invalid.'])->withInput();
            }
        }
        $user = auth()->user();

        if (!$user->plan_id) {
            $notify[] = ['error', 'You do not have any active subscription plan.'];
            return back()->withNotify($notify);
        }

        if ($user->expired_date < now()->format('Y-m-d')) {
            $notify[] = ['error', 'Your subscription plan has expired. Please renew to continue using the service.'];
            return back()->withNotify($notify);
        }

        $globalCommitted = array_map('intval', (array) ($submitExam->committed_questions ?? []));
        $flowCommitted = $this->globalIndicesToFlowIndices($globalCommitted, $flowQuestionIds, $allQuestionIds);
        $flowScoreData = ExamScoreCalculator::evaluate(
            $flowQuestions,
            $request->input('answer', []),
            $storedAnswerData,
            $flowCommitted,
            (float) $exam->pass_percentage
        );

        // Replace only target questions that received a new answer. Skipped
        // retake questions retain their previous answer and score.
        $replacementQuestionIds = [];
        foreach ($flowQuestionIds as $questionId) {
            $question = $questionsById->get($questionId);
            $hasChoice = $question?->question_type !== 'drag_drop'
                && isset($storedAnswers[$questionId])
                && (is_array($storedAnswers[$questionId])
                    ? count($storedAnswers[$questionId]) > 0
                    : $storedAnswers[$questionId] !== null && $storedAnswers[$questionId] !== '');
            $hasDragAnswer = $question?->question_type === 'drag_drop'
                && !empty($storedAnswerData[$questionId] ?? []);
            if ($hasChoice || $hasDragAnswer) {
                $replacementQuestionIds[] = $questionId;
            }
        }

        $data = [];
        foreach ($request->input('answer', []) as $questionId => $answer) {
            $question = $questionsById->get((string) $questionId);
            if (!in_array((string) $questionId, $replacementQuestionIds, true)) {
                continue;
            }
            $optionIds = is_array($answer) ? $answer : [$answer];
            foreach ($optionIds as $optionId) {
                $data[] = [
                    'exam_attend_id' => $submitExam->id,
                    'question_id'    => $questionId,
                    'option_id'      => $question->question_type === 'drag_drop' ? 0 : $optionId,
                    'answer_data'    => $question->question_type === 'drag_drop'
                        ? json_encode($storedAnswerData[(string) $questionId] ?? [])
                        : null,
                    'is_correct'     => $flowScoreData['question_results'][(string) $questionId]['is_correct'] ? Status::YES : Status::NO,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
        }

        DB::transaction(function () use ($submitExam, $replacementQuestionIds, $data, $exam, $allQuestionIds, $globalCommitted) {
            if ($replacementQuestionIds) {
                $submitExam->examAnswer()->whereIn('question_id', $replacementQuestionIds)->delete();
            }
            if ($data) {
                ExamAnswer::insert($data);
            }

            $allQuestions = collect($allQuestionIds)
                ->map(fn ($questionId) => $exam->questions->firstWhere('id', (int) $questionId))
                ->filter()
                ->values();
            $answerRows = $submitExam->examAnswer()->get();
            $aggregateScore = $this->scoreStoredAttempt(
                $allQuestions,
                $answerRows,
                $globalCommitted,
                (float) $exam->pass_percentage
            );
            $this->persistCalculatedResult($submitExam, $answerRows, $aggregateScore);

            $submitExam->retake_question_ids = null;
            $submitExam->session_answers = [];
            $submitExam->session_answer_data = [];
            $submitExam->remaining_seconds = null;
            $submitExam->is_paused = false;
            $submitExam->current_question = 0;
            $submitExam->save();
        });

        return to_route('user.exam.view', $submitExam->id)->withNotify(['success', 'You have successfully submitted the exam.']);

    }

    public function retake($id, $mode)
    {
        abort_unless(in_array($mode, ['all', 'missed', 'incorrect', 'marked'], true), 404);

        $attendExam = AttendExam::where('user_id', auth()->id())->findOrFail($id);
        abort_unless((int) $attendExam->is_submit === Status::YES, 404);

        $exam = Exam::active()->running()->whereKey($attendExam->exam_id)->withWhereHas('questions', function ($query) {
            $query->active()->select([
                'id', 'exam_id', 'title', 'question_type', 'answer_data', 'result_option_id', 'sort_order'
            ])->orderBy('sort_order')->orderBy('id')->with('options', function ($q) {
                $q->active()->select(['id', 'question_id', 'title']);
            });
        })->firstOrFail();

        $allQuestionIds = $this->orderedQuestionIds($exam);
        $questions = collect($allQuestionIds)
            ->map(fn ($questionId) => $exam->questions->firstWhere('id', (int) $questionId))
            ->filter()
            ->values();
        $answerRows = $attendExam->examAnswer()->get();

        if ($mode === 'all') {
            $targetIds = $allQuestionIds;
        } elseif ($mode === 'marked') {
            $markedIndices = array_fill_keys(array_map('intval', (array) ($attendExam->flagged_questions ?? [])), true);
            $targetIds = [];
            foreach ($allQuestionIds as $index => $questionId) {
                if (isset($markedIndices[$index])) {
                    $targetIds[] = $questionId;
                }
            }
        } elseif (in_array($mode, ['missed', 'incorrect'], true)) {
            $scoreData = $this->scoreStoredAttempt(
                $questions,
                $answerRows,
                array_map('intval', (array) ($attendExam->committed_questions ?? [])),
                (float) $exam->pass_percentage
            );
            $targetIds = [];
            foreach ($allQuestionIds as $questionId) {
                $result = $scoreData['question_results'][$questionId] ?? [];
                $matchesMode = $mode === 'missed'
                    ? empty($result['answered'])
                    : !empty($result['answered']) && empty($result['is_correct']);
                if ($matchesMode) {
                    $targetIds[] = $questionId;
                }
            }
        }

        if (!$targetIds) {
            $message = match ($mode) {
                'marked' => 'There are no marked questions to retake.',
                'missed' => 'There are no missed questions to retake.',
                'incorrect' => 'There are no incorrect questions to retake.',
                default => 'There are no questions to retake.',
            };
            $notify[] = ['error', $message];
            return back()->withNotify($notify);
        }

        $targetGlobalIndices = [];
        foreach ($targetIds as $questionId) {
            $globalIndex = array_search($questionId, $allQuestionIds, true);
            if ($globalIndex !== false) {
                $targetGlobalIndices[] = $globalIndex;
            }
        }
        $preservedCommits = array_values(array_diff(
            array_map('intval', (array) ($attendExam->committed_questions ?? [])),
            $targetGlobalIndices
        ));
        $preservedReveals = array_values(array_diff(
            array_map('intval', (array) ($attendExam->revealed_questions ?? [])),
            $targetGlobalIndices
        ));

        $attendExam->is_submit = Status::NO;
        $attendExam->status = Status::EXAM_INITIATE;
        $attendExam->start_time = now();
        $attendExam->end_time = null;
        $settings = $attendExam->exam_settings
            ? $this->settingsForAttempt($attendExam)
            : array_merge($this->defaultExamSettings(), ['timer' => 'legacy_duration']);
        $attendExam->is_paused = false;
        $attendExam->remaining_seconds = ($settings['timer'] ?? null) === 'legacy_duration'
            ? (int) $exam->duration * 60
            : $this->timerSeconds((string) ($settings['timer'] ?? 'not_timed'), count($targetIds));
        $attendExam->current_question = 0;
        $attendExam->retake_question_ids = $targetIds;
        $attendExam->session_answers = [];
        $attendExam->session_answer_data = [];
        $attendExam->committed_questions = $preservedCommits;
        $attendExam->revealed_questions = $preservedReveals;
        $attendExam->save();

        return to_route('user.exam.resume', $attendExam->id);
    }

    public function history() {
        $pageTitle = 'Exam History';
        $examLogs  = AttendExam::where('user_id', auth()->id())->with('exam')->searchable(['exam:title'])->latest()->paginate(getPaginate());

        $examIds = $examLogs->getCollection()->pluck('exam_id')->filter()->unique()->values();
        $attemptIds = $examLogs->getCollection()->pluck('id');
        $questionsByExam = Question::active()
            ->whereIn('exam_id', $examIds)
            ->select(['id', 'exam_id', 'question_type', 'answer_data', 'result_option_id', 'sort_order'])
            ->with('options', function ($query) {
                $query->active()->select(['id', 'question_id']);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('exam_id');
        $answersByAttempt = ExamAnswer::whereIn('exam_attend_id', $attemptIds)->get()->groupBy('exam_attend_id');

        foreach ($examLogs as $examLog) {
            $questions = $questionsByExam->get($examLog->exam_id, collect())->values();
            $answers = $answersByAttempt->get($examLog->id, collect());
            $scoreData = $this->scoreStoredAttempt(
                $questions,
                $answers,
                array_map('intval', (array) ($examLog->committed_questions ?? [])),
                (float) ($examLog->exam?->pass_percentage ?? 0)
            );
            $examLog->setAttribute('score_percentage', $scoreData['percentage']);
            $examLog->setAttribute('passed', $scoreData['passed']);
            $examLog->setAttribute('history_start_time', $examLog->start_time ?? $examLog->created_at);
            $examLog->setAttribute(
                'history_end_time',
                $examLog->end_time ?? ((int) $examLog->status === Status::EXAM_COMPLETED ? $examLog->updated_at : null)
            );
            $markedIndices = array_unique(array_map('intval', (array) ($examLog->flagged_questions ?? [])));
            $markedCount = count(array_filter($markedIndices, fn ($index) => $index >= 0 && $index < $questions->count()));
            $incorrectCount = count(array_filter(
                $scoreData['question_results'],
                fn ($result) => !empty($result['answered']) && empty($result['is_correct'])
            ));

            $examLog->setAttribute('retake_question_counts', [
                'all' => $questions->count(),
                'missed' => count(array_filter(
                    $scoreData['question_results'],
                    fn ($result) => empty($result['answered'])
                )),
                'incorrect' => $incorrectCount,
                'marked' => $markedCount,
            ]);
        }

        return view('Template::user.exams.history', compact('pageTitle', 'examLogs'));
    }

    public function deleteAttempt($id) {
        $userId = auth()->id();

        DB::transaction(function () use ($id, $userId) {
            $attempt = AttendExam::where('user_id', $userId)
                ->lockForUpdate()
                ->findOrFail($id);

            $attempt->certificateUser()->delete();
            $attempt->examAnswer()->delete();
            $attempt->delete();
        });

        $notify[] = ['success', 'The exam attempt has been deleted.'];
        return to_route('user.exam.history')->withNotify($notify);
    }

    private function orderedQuestionIds(Exam $exam): array
    {
        return $exam->questions()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    private function flowQuestionIds(array $allQuestionIds, AttendExam $attendExam): array
    {
        $savedOrder = array_map('strval', (array) ($attendExam->question_order ?? []));
        if ($savedOrder) {
            $validIds = array_fill_keys($allQuestionIds, true);
            $savedOrder = array_values(array_unique(array_filter($savedOrder, fn ($id) => isset($validIds[$id]))));
            $savedSet = array_fill_keys($savedOrder, true);
            $savedOrder = array_merge($savedOrder, array_values(array_filter($allQuestionIds, fn ($id) => !isset($savedSet[$id]))));
            $allQuestionIds = $savedOrder;
        }

        $retakeIds = array_map('strval', (array) ($attendExam->retake_question_ids ?? []));
        if (!$retakeIds) {
            return $allQuestionIds;
        }

        $retakeSet = array_fill_keys($retakeIds, true);
        return array_values(array_filter($allQuestionIds, fn ($id) => isset($retakeSet[$id])));
    }

    private function defaultExamSettings(): array
    {
        return [
            'question_order' => 'original',
            'randomize_options' => false,
            'show_score_live' => true,
            'include_show_answer' => true,
            'inline_feedback' => true,
            'timer' => 'not_timed',
        ];
    }

    private function settingsForAttempt(AttendExam $attempt): array
    {
        return array_merge($this->defaultExamSettings(), (array) ($attempt->exam_settings ?? []));
    }

    private function timerSeconds(string $timer, int $questionCount): ?int
    {
        return match ($timer) {
            'ccna_120' => 120 * 60,
            '90_mins' => 90 * 60,
            '60_mins' => 60 * 60,
            '30s_per_q' => $questionCount * 30,
            '60s_per_q' => $questionCount * 60,
            '90s_per_q' => $questionCount * 90,
            default => null,
        };
    }

    private function timerLabel(array $settings, int $questionCount): string
    {
        if (($settings['timer'] ?? 'not_timed') === 'legacy_duration') {
            return 'Timed session';
        }
        $seconds = $this->timerSeconds((string) ($settings['timer'] ?? 'not_timed'), $questionCount);
        if ($seconds === null) {
            return 'Untimed';
        }
        if (in_array($settings['timer'], ['30s_per_q', '60s_per_q', '90s_per_q'], true)) {
            return (int) ($seconds / max(1, $questionCount)) . ' sec / question';
        }
        return (int) ($seconds / 60) . ' minutes';
    }

    private function loadQuestionForAttempt(Exam $exam, string $questionId, AttendExam $attempt): Question
    {
        $question = $this->loadQuestionById($exam, $questionId);
        $order = (array) (($attempt->option_orders ?? [])[$questionId] ?? []);
        if ($order) {
            $positions = array_flip(array_map('intval', $order));
            $question->setRelation('options', $question->options
                ->sortBy(fn ($option) => $positions[(int) $option->id] ?? PHP_INT_MAX)
                ->values());
        }
        return $question;
    }

    private function liveScoreForAttempt(Exam $exam, AttendExam $attempt, array $allQuestionIds, array $flowQuestionIds, bool $includeQuestionResults = false): array
    {
        $questions = $exam->questions()->active()
            ->orderBy('sort_order')->orderBy('id')
            ->select(['id', 'exam_id', 'title', 'question_type', 'answer_data', 'result_option_id', 'sort_order'])
            ->with('options', fn ($query) => $query->active()->select(['id', 'question_id', 'title']))
            ->get()->keyBy(fn ($question) => (string) $question->id);
        $globalCommitted = array_map('intval', (array) ($attempt->committed_questions ?? []));
        $committedFlow = $this->globalIndicesToFlowIndices($globalCommitted, $flowQuestionIds, $allQuestionIds);
        $committedIds = [];
        foreach ($committedFlow as $flowIndex) {
            if (isset($flowQuestionIds[$flowIndex])) {
                $committedIds[$flowQuestionIds[$flowIndex]] = true;
            }
        }

        $answers = [];
        $answerData = [];
        foreach ($attempt->examAnswer()->get()->groupBy('question_id') as $questionId => $rows) {
            $question = $questions->get((string) $questionId);
            $ids = $rows->pluck('option_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
            if ($ids) {
                $answers[(string) $questionId] = count($ids) === 1 ? $ids[0] : $ids;
            }
            if ($question?->question_type === 'drag_drop') {
                $answerData[(string) $questionId] = ExamScoreCalculator::decodeAnswerData($rows->first()?->answer_data);
            }
        }
        $sessionAnswers = (array) ($attempt->session_answers ?? []);
        $sessionData = (array) ($attempt->session_answer_data ?? []);
        foreach (array_keys($committedIds) as $questionId) {
            if (array_key_exists($questionId, $sessionAnswers)) {
                $answers[$questionId] = $sessionAnswers[$questionId];
            }
            if (array_key_exists($questionId, $sessionData)) {
                $answerData[$questionId] = $sessionData[$questionId];
            }
        }

        $score = ExamScoreCalculator::evaluate(
            $questions->values(),
            $answers,
            $answerData,
            $globalCommitted,
            (float) $exam->pass_percentage
        );
        $liveScore = [
            'correct' => $score['correct'],
            'total_questions' => $score['total_questions'],
            'percentage' => $score['percentage'],
            'evaluated' => count($committedIds),
        ];
        if ($includeQuestionResults) {
            $liveScore['question_results'] = $score['question_results'];
        }
        return $liveScore;
    }

    private function globalIndicesToFlowIndices(array $globalIndices, array $flowQuestionIds, array $allQuestionIds): array
    {
        $globalSet = array_fill_keys(array_map('intval', $globalIndices), true);
        $flowIndices = [];
        foreach ($flowQuestionIds as $flowIndex => $questionId) {
            $globalIndex = array_search($questionId, $allQuestionIds, true);
            if ($globalIndex !== false && isset($globalSet[$globalIndex])) {
                $flowIndices[] = $flowIndex;
            }
        }
        return $flowIndices;
    }

    private function mergeFlowIndices(array $existingGlobalIndices, array $incomingFlowIndices, array $flowQuestionIds, array $allQuestionIds): array
    {
        $targetGlobalIndices = [];
        foreach ($flowQuestionIds as $questionId) {
            $globalIndex = array_search($questionId, $allQuestionIds, true);
            if ($globalIndex !== false) {
                $targetGlobalIndices[] = $globalIndex;
            }
        }

        $targetSet = array_fill_keys($targetGlobalIndices, true);
        $merged = array_values(array_filter(
            array_map('intval', $existingGlobalIndices),
            fn ($index) => !isset($targetSet[$index])
        ));
        $incomingSet = array_fill_keys(array_map('intval', $incomingFlowIndices), true);

        foreach ($flowQuestionIds as $flowIndex => $questionId) {
            if (!isset($incomingSet[$flowIndex])) {
                continue;
            }
            $globalIndex = array_search($questionId, $allQuestionIds, true);
            if ($globalIndex !== false) {
                $merged[] = $globalIndex;
            }
        }

        $merged = array_values(array_unique(array_map('intval', $merged)));
        sort($merged);
        return $merged;
    }

    private function loadQuestionById(Exam $exam, string $questionId)
    {
        return $exam->questions()
            ->active()
            ->whereKey($questionId)
            ->select([
                'id', 'exam_id', 'title', 'question_type', 'explanation',
                'exhibit_image', 'original_source_image', 'cli_snippet',
                'answer_data', 'result_option_id', 'sort_order'
            ])
            ->with('options', function ($query) {
                $query->active()->select(['id', 'question_id', 'title']);
            })
            ->firstOrFail();
    }

    private function loadQuestion(Exam $exam, int $index) {
        return $exam->questions()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->select([
                'id', 'exam_id', 'title', 'question_type', 'explanation',
                'exhibit_image', 'original_source_image', 'cli_snippet',
                'answer_data', 'result_option_id', 'sort_order'
            ])
            ->with('options', function ($query) {
                $query->active()->select(['id', 'question_id', 'title']);
            })
            ->skip($index)
            ->firstOrFail();
    }

    private function scoreStoredAttempt(Collection $questions, Collection $answerRows, array $committedQuestionIndices, float $passingThreshold): array
    {
        $answers = [];
        $answerData = [];

        foreach ($answerRows->groupBy('question_id') as $questionId => $rows) {
            $selectedOptionIds = $rows->pluck('option_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
            if ($selectedOptionIds) {
                $answers[(string) $questionId] = count($selectedOptionIds) === 1 ? $selectedOptionIds[0] : $selectedOptionIds;
            }

            $question = $questions->firstWhere('id', (int) $questionId);
            if ($question?->question_type === 'drag_drop') {
                $answerData[(string) $questionId] = ExamScoreCalculator::decodeAnswerData($rows->first()?->answer_data);
            }
        }

        return ExamScoreCalculator::evaluate($questions, $answers, $answerData, $committedQuestionIndices, $passingThreshold);
    }

    private function persistCalculatedResult(AttendExam $attendExam, Collection $answerRows, array $scoreData): void
    {
        foreach ($answerRows as $answerRow) {
            $isCorrect = (bool) ($scoreData['question_results'][(string) $answerRow->question_id]['is_correct'] ?? false);
            $answerRow->is_correct = $isCorrect ? Status::YES : Status::NO;
            $answerRow->save();
        }

        $attendExam->is_submit = Status::YES;
        $attendExam->status = Status::EXAM_COMPLETED;
        $attendExam->end_time = $attendExam->end_time ?: now();
        $attendExam->correct_answer = $scoreData['correct'];
        $attendExam->pass_percentage = $scoreData['percentage'];
        $attendExam->save();
    }

    public function view($id) {
        $attendExam = AttendExam::where('user_id', auth()->id())->findOrFail($id);
        $answerRows = $attendExam->examAnswer()->get();
        $isSubmitted = (int) $attendExam->is_submit === Status::YES;

        $exam = Exam::where('id', $attendExam->exam_id)->with('category')->firstOrFail();
        $attemptSettings = $attendExam->exam_settings
            ? $this->settingsForAttempt($attendExam)
            : array_merge($this->defaultExamSettings(), ['timer' => 'legacy_duration']);
        $attemptTimerLabel = ($attemptSettings['timer'] ?? null) === 'legacy_duration'
            ? (int) $exam->duration . ' minutes'
            : $this->timerLabel($attemptSettings, $exam->questions()->active()->count());
        $questions = $exam->questions()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->select(['id', 'exam_id', 'question_type', 'answer_data', 'result_option_id', 'sort_order'])
            ->with('options', function ($query) {
                $query->active()->select(['id', 'question_id']);
            })
            ->get();

        $totalQuestions = $questions->count();
        $scoreData = null;
        if ($isSubmitted) {
            $scoreData = $this->scoreStoredAttempt(
                $questions,
                $answerRows,
                (array) ($attendExam->committed_questions ?? []),
                (float) $exam->pass_percentage
            );

            if ((int) $attendExam->status !== Status::EXAM_COMPLETED) {
                $this->persistCalculatedResult($attendExam, $answerRows, $scoreData);
            }
        }

        $exam->unsetRelation('questions');
        $attendExam->setRelation('exam', $exam);
        $pageTitle = $isSubmitted ? 'Exam Result' : 'Exam in Progress';

        return view('Template::user.exams.view', compact('pageTitle', 'exam', 'attendExam', 'scoreData', 'totalQuestions', 'attemptTimerLabel'));
    }

    public function review($id, $index = 0) {
        $attendExam = AttendExam::where('user_id', auth()->id())->findOrFail($id);
        abort_unless((int) $attendExam->is_submit === Status::YES, 404);

        $exam = Exam::where('id', $attendExam->exam_id)->with('category')->firstOrFail();
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $reviewQuestionIds = $this->flowQuestionIds($allQuestionIds, $attendExam);
        $questionCount = count($reviewQuestionIds);
        $index = (int) $index;
        abort_if($index < 0 || $index >= $questionCount, 404);

        $question = $this->loadQuestionForAttempt($exam, $reviewQuestionIds[$index], $attendExam);
        $answerRows = $attendExam->examAnswer()->where('question_id', $question->id)->get();
        $canonicalIndex = array_search((string) $question->id, $allQuestionIds, true);
        $committedAtIndex = $canonicalIndex !== false && in_array((int) $canonicalIndex, array_map('intval', (array) ($attendExam->committed_questions ?? [])), true);
        $scoreData = $this->scoreStoredAttempt(
            collect([$question]),
            $answerRows,
            $committedAtIndex ? [0] : [],
            (float) $exam->pass_percentage
        );
        $questionResult = $scoreData['question_results'][(string) $question->id] ?? [];
        $selectedOptionIds = $answerRows->pluck('option_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->all();
        $pageTitle = 'Review Exam';

        return view('Template::user.exams.review', compact(
            'pageTitle', 'exam', 'attendExam', 'question', 'questionResult', 'selectedOptionIds', 'questionCount', 'index'
        ));
    }

    public function certificateDownload($id) {
        $attendExam = AttendExam::completed()->where('user_id', auth()->id())->where('id', $id)->with('exam', 'user')->firstOrFail();

        $user = auth()->user();

        $user = $attendExam->user;
        if (!$user) {
            abort(404);
        }
        $exam = $attendExam->exam;
        if (!$exam) {
            abort(404);
        }

        $getCertificateData = GetCertificateUser::where('user_id', $user->id)->where('attend_exam_id', $attendExam->id)->first();

        if (!$getCertificateData) {
            $secret                             = getTrx();
            $getCertificateData                 = new GetCertificateUser();
            $getCertificateData->user_id        = $user->id;
            $getCertificateData->attend_exam_id = $attendExam->id;
            $getCertificateData->secret         = $secret;
            $getCertificateData->save();
        }

        $url = gs('verify_url') . '/' . $getCertificateData->secret;

        $qrCodeUrl = certificateQr($url);

        $general = gs();

        $message = "You achieved {$attendExam->pass_percentage}% in your exam. Well done!";

        $qrCode = '<img  src="data:image/png;base64,' . base64_encode(file_get_contents($qrCodeUrl)) . '" />';
        $logoPath = base_path('../' . getFilePath('logoIcon') . '/logo.png');
        $logo   = '<img src="data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) . '" />';

        $certificate = str_replace("{{fullname}}", $user->firstname . ' ' . $user->lastname, $general->certificate_template);
        $certificate = str_replace("{{site_name}}", $general->site_name, $certificate);
        $certificate = str_replace("{{qr_code}}", $qrCode, $certificate);
        $certificate = str_replace("{{site_logo}}", $logo, $certificate);
        $certificate = str_replace("{{exam_category}}", $exam->category?->name ?? $exam->title, $certificate);
        $certificate = str_replace("{{exam_title}}", $exam->title, $certificate);
        $certificate = str_replace("{{exam_score}}", getAmount($attendExam->pass_percentage) . '%', $certificate);
        $certificate = str_replace("{{exam_completion_date}}", showDateTime($exam->created_at, 'F j, Y'), $certificate);
        $certificate = str_replace("{{exam_based_message}}", $message, $certificate);

        $data = [
            'certificate' => $certificate,
        ];

        $pdf = PDF::loadView('certificate_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('dpi', 110)
            ->setOption('defaultFont', 'sans-serif');

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="certificate.pdf"');
    }

}
