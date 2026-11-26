<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Support\ExamScoreCalculator;
use App\Models\AttendExam;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\GetCertificateUser;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExamAttemptController extends Controller
{
    use ApiV1Trait;

    public function startAttempt(Request $request, $examId)
    {
        $exam = Exam::active()->running()->find($examId);
        if (!$exam) {
            return $this->fail('Exam not found.', 404);
        }

        $allQuestionIds = $this->orderedQuestionIds($exam);
        if (!$allQuestionIds) {
            return $this->fail('This exam has no active questions.', 422);
        }

        $user = $request->user();
        if (!$user->plan_id || !$user->expired_date || $user->expired_date < now()->format('Y-m-d')) {
            return $this->fail('Your active subscription is required to start this exam.', 422);
        }
        if ((int) $user->exam_limit <= 0) {
            return $this->fail('You do not have enough credit to perform this action.', 422);
        }

        $validator = Validator::make($request->all(), [
            'mode' => 'required|in:study,simulation',
            'question_order' => 'required|in:original,random',
            'randomize_options' => 'nullable|boolean',
            'show_score_live' => 'nullable|boolean',
            'include_show_answer' => 'nullable|boolean',
            'inline_feedback' => 'nullable|boolean',
            'timer' => 'required|in:not_timed,ccna_120,90_mins,60_mins,30s_per_q,60s_per_q,90s_per_q',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $settings = [
            'mode' => $request->input('mode'),
            'question_order' => $request->input('question_order'),
            'randomize_options' => $request->boolean('randomize_options'),
            'show_score_live' => $request->boolean('show_score_live'),
            'include_show_answer' => $request->boolean('include_show_answer'),
            'inline_feedback' => $request->boolean('inline_feedback'),
            'timer' => $request->input('timer'),
        ];

        if ($settings['mode'] === 'simulation') {
            $simulationPercent = random_int(70, 80);
            $simulationCount = $exam->isAggregate()
                ? random_int(70, 80)
                : max(1, (int) round(count($allQuestionIds) * ($simulationPercent / 100)));

            $settings['question_order'] = 'random';
            $settings['timer'] = '90_mins';
            $settings['include_show_answer'] = false;
            $settings['inline_feedback'] = false;
            $settings['show_score_live'] = true;
            $settings['simulation_percent'] = $simulationPercent;
            $settings['simulation_count'] = min($simulationCount, count($allQuestionIds));
            $orderedIds = collect($allQuestionIds)->shuffle()->take($settings['simulation_count'])->values()->all();
        } else {
            $orderedIds = $settings['question_order'] === 'random'
                ? collect($allQuestionIds)->shuffle()->values()->all()
                : $allQuestionIds;
        }

        $questionOptions = Question::whereIn('id', $orderedIds)
            ->with(['options' => fn ($q) => $q->active()->orderBy('id')])
            ->get()
            ->keyBy(fn ($q) => (string) $q->id);

        $optionOrders = [];
        foreach ($orderedIds as $questionId) {
            $optionIds = $questionOptions->get($questionId)?->options->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
            $optionOrders[$questionId] = $settings['randomize_options']
                ? collect($optionIds)->shuffle()->values()->all()
                : $optionIds;
        }

        $seconds = $this->timerSeconds($settings['timer'], count($orderedIds));

        $attemptId = DB::transaction(function () use ($user, $exam, $settings, $orderedIds, $optionOrders, $seconds) {
            $lockedUser = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            if ((int) $lockedUser->exam_limit <= 0) {
                abort(422, 'You do not have enough credit to perform this action.');
            }

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

        return $this->ok(['attempt_id' => (int) $attemptId], 'Exam attempt started.', 201);
    }

    public function history(Request $request)
    {
        $userId = $request->user()->id;
        $search = trim((string) $request->query('search', ''));

        $query = AttendExam::where('user_id', $userId)
            ->with(['exam.category', 'examAnswer', 'certificateUser'])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->whereHas('exam', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $logs = $query->get();

        $data = $logs->map(function (AttendExam $log) {
            $exam = $log->exam;
            $allIds = $exam ? $this->orderedQuestionIds($exam) : [];
            $flowIds = $exam ? $this->flowQuestionIds($allIds, $log) : [];
            $totalQuestions = count($flowIds);
            $correctCount = (int) ($log->correct_answer ?? 0);
            $scorePct = (float) ($log->pass_percentage ?? 0.0);
            $passMark = (float) ($exam?->pass_percentage ?? 82.5);
            $isSubmitted = (int) $log->is_submit === Status::YES;

            $retakeCounts = [
                'all' => $totalQuestions,
                'missed' => 0,
                'incorrect' => 0,
                'marked' => count((array) ($log->flagged_questions ?? [])),
            ];

            if ($isSubmitted && $exam && $flowIds) {
                $questions = $this->loadQuestionsByIds($exam, $flowIds, $log);
                $score = $this->scoreStoredAttempt($questions, $log->examAnswer, [], $passMark);
                $retakeCounts['missed'] = (int) ($score['unanswered'] ?? 0);
                $retakeCounts['incorrect'] = (int) ($score['incorrect'] ?? 0);
                $correctCount = (int) ($score['correct'] ?? $correctCount);
                $scorePct = (float) ($score['percentage'] ?? $scorePct);
            }

            return [
                'id' => (int) $log->id,
                'exam_id' => (int) $log->exam_id,
                'exam_title' => (string) ($exam?->title ?? 'Exam'),
                'category_name' => (string) ($exam?->category?->name ?? ''),
                'is_submitted' => $isSubmitted,
                'status' => (int) ($log->status ?? 0),
                'start_time_millis' => $log->start_time ? \Carbon\Carbon::parse($log->start_time)->getTimestampMs() : 0,
                'end_time_millis' => $log->end_time ? \Carbon\Carbon::parse($log->end_time)->getTimestampMs() : null,
                'score_percentage' => $scorePct,
                'pass_mark_percentage' => $passMark,
                'passed' => $isSubmitted && $scorePct >= $passMark,
                'total_questions' => $totalQuestions,
                'correct_count' => $correctCount,
                'retake_counts' => $retakeCounts,
                'has_certificate' => (bool) $log->certificateUser,
            ];
        })->values()->all();

        return $this->ok($data);
    }

    public function session(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::NO)
            ->find($attemptId);

        if (!$attempt) {
            return $this->fail('Active exam session not found.', 404);
        }

        $exam = Exam::active()->running()->with('category')->find($attempt->exam_id);
        if (!$exam) {
            return $this->fail('Exam not found.', 404);
        }

        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $flowQuestions = $this->loadQuestionsByIds($exam, $flowQuestionIds, $attempt);

        $settings = array_merge($this->defaultExamSettings(), (array) ($attempt->exam_settings ?? []));
        $liveScore = $this->liveScoreForAttempt($exam, $attempt, $allQuestionIds, $flowQuestionIds, $flowQuestions);

        $committedFlowIndices = $this->globalIndicesToFlowIndices((array) ($attempt->committed_questions ?? []), $flowQuestionIds, $allQuestionIds);
        $flaggedFlowIndices = $this->globalIndicesToFlowIndices((array) ($attempt->flagged_questions ?? []), $flowQuestionIds, $allQuestionIds);
        $revealedFlowIndices = $this->globalIndicesToFlowIndices((array) ($attempt->revealed_questions ?? []), $flowQuestionIds, $allQuestionIds);

        $incorrectFlowIndices = [];
        foreach ($flowQuestionIds as $idx => $qId) {
            $res = $liveScore['question_results'][$qId] ?? null;
            if ($res && !empty($res['answered']) && empty($res['is_correct'])) {
                $incorrectFlowIndices[] = $idx;
            }
        }

        $sessionAnswers = [];
        foreach ((array) ($attempt->session_answers ?? []) as $qId => $val) {
            $arr = is_array($val) ? array_values(array_map('intval', $val)) : [(int) $val];
            if ($arr) {
                $sessionAnswers[(string) $qId] = $arr;
            }
        }

        $sessionAnswerData = [];
        foreach ((array) ($attempt->session_answer_data ?? []) as $qId => $map) {
            if (is_array($map) && $map) {
                $sessionAnswerData[(string) $qId] = (object) array_map('strval', $map);
            }
        }

        $dragFeedbackByQuestionId = [];
        $committedSet = array_flip($committedFlowIndices);
        foreach ($flowQuestions as $idx => $question) {
            if ($question->question_type === 'drag_drop' && isset($committedSet[$idx])) {
                $submitted = (array) (($attempt->session_answer_data ?? [])[(string) $question->id] ?? []);
                $dragFeedbackByQuestionId[(string) $question->id] = $this->dragDropFeedbackForQuestion($question, $submitted);
            }
        }

        return $this->ok([
            'attempt_id' => (int) $attempt->id,
            'exam' => $this->formatExam($exam, true),
            'settings' => $this->formatExamSettings($settings),
            'flow_questions' => $flowQuestions->map(fn ($q) => $this->formatQuestion($q))->values()->all(),
            'all_question_ids' => array_map('intval', $allQuestionIds),
            'flow_question_ids' => array_map('intval', $flowQuestionIds),
            'current_question_index' => max(0, min(max(0, count($flowQuestionIds) - 1), (int) ($attempt->current_question ?? 0))),
            'remaining_seconds' => $attempt->remaining_seconds !== null ? (int) $attempt->remaining_seconds : null,
            'is_paused' => (bool) ($attempt->is_paused ?? false),
            'session_answers' => (object) $sessionAnswers,
            'session_answer_data' => (object) $sessionAnswerData,
            'flagged_flow_indices' => array_values($flaggedFlowIndices),
            'revealed_flow_indices' => array_values($revealedFlowIndices),
            'committed_flow_indices' => array_values($committedFlowIndices),
            'incorrect_flow_indices' => array_values($incorrectFlowIndices),
            'live_score' => [
                'correct' => (int) ($liveScore['correct'] ?? 0),
                'total_questions' => (int) ($liveScore['total_questions'] ?? 0),
                'percentage' => (float) ($liveScore['percentage'] ?? 0),
                'evaluated' => (int) ($liveScore['evaluated'] ?? $liveScore['answered'] ?? 0),
            ],
            'drag_feedback_by_question_id' => (object) $dragFeedbackByQuestionId,
        ]);
    }

    public function saveState(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::NO)
            ->find($attemptId);

        if (!$attempt) {
            return $this->fail('Active exam session not found.', 404);
        }

        $exam = Exam::active()->running()->findOrFail($attempt->exam_id);
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attempt);

        $attempt->current_question = max(0, min(max(0, count($flowQuestionIds) - 1), (int) $request->input('current_question_index', 0)));
        if ($request->has('remaining_seconds')) {
            $rem = $request->input('remaining_seconds');
            $attempt->remaining_seconds = $rem === null ? null : max(0, (int) $rem);
        }
        if ($request->has('is_paused')) {
            $attempt->is_paused = $request->boolean('is_paused');
        }

        $attempt->session_answers = $this->normalizeSessionAnswers((array) $request->input('session_answers', []));
        $attempt->session_answer_data = $this->normalizeSessionAnswerData((array) $request->input('session_answer_data', []));
        $attempt->flagged_questions = $this->mergeFlowIndices(
            (array) ($attempt->flagged_questions ?? []),
            (array) $request->input('flagged_flow_indices', []),
            $flowQuestionIds,
            $allQuestionIds
        );
        $attempt->revealed_questions = $this->mergeFlowIndices(
            (array) ($attempt->revealed_questions ?? []),
            (array) $request->input('revealed_flow_indices', []),
            $flowQuestionIds,
            $allQuestionIds
        );
        $attempt->committed_questions = $this->mergeFlowIndices(
            (array) ($attempt->committed_questions ?? []),
            (array) $request->input('committed_flow_indices', []),
            $flowQuestionIds,
            $allQuestionIds
        );
        $attempt->save();

        $live = $this->liveScoreForAttempt($exam, $attempt, $allQuestionIds, $flowQuestionIds);

        return $this->ok([
            'live_score' => [
                'correct' => (int) ($live['correct'] ?? 0),
                'total_questions' => (int) ($live['total_questions'] ?? 0),
                'percentage' => (float) ($live['percentage'] ?? 0),
                'evaluated' => (int) ($live['evaluated'] ?? $live['answered'] ?? 0),
            ],
        ]);
    }

    public function confirmDragDrop(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::NO)
            ->find($attemptId);

        if (!$attempt) {
            return $this->fail('Active exam session not found.', 404);
        }

        $exam = Exam::active()->running()->findOrFail($attempt->exam_id);
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $flowIndex = (int) $request->input('flow_index', -1);

        if ($flowIndex < 0 || $flowIndex >= count($flowQuestionIds)) {
            return $this->fail('Invalid question index.', 422);
        }

        $questionId = $flowQuestionIds[$flowIndex];
        $question = $this->loadQuestionsByIds($exam, [$questionId], $attempt)->first();
        if (!$question) {
            return $this->fail('Question not found.', 404);
        }

        $submittedMatches = array_map('strval', (array) $request->input('submitted_matches', []));
        $answers = $this->normalizeSessionAnswers((array) $request->input('session_answers', $attempt->session_answers ?? []));
        $answerData = $this->normalizeSessionAnswerData((array) $request->input('session_answer_data', $attempt->session_answer_data ?? []));

        if ($submittedMatches) {
            $answers[$questionId] = 1;
            $answerData[$questionId] = $submittedMatches;
        }

        $attempt->session_answers = $answers;
        $attempt->session_answer_data = $answerData;
        $attempt->committed_questions = $this->mergeFlowIndices(
            (array) ($attempt->committed_questions ?? []),
            [$flowIndex],
            $flowQuestionIds,
            $allQuestionIds
        );
        $attempt->save();

        return $this->ok($this->dragDropFeedbackForQuestion($question, $submittedMatches));
    }

    public function revealAnswer(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::NO)
            ->find($attemptId);

        if (!$attempt) {
            return $this->fail('Active exam session not found.', 404);
        }

        $settings = array_merge($this->defaultExamSettings(), (array) ($attempt->exam_settings ?? []));
        if (empty($settings['include_show_answer'])) {
            return $this->fail('Show Answer is disabled in Simulation mode.', 403);
        }

        $exam = Exam::active()->running()->findOrFail($attempt->exam_id);
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $flowIndex = (int) $request->input('flow_index', -1);

        if ($flowIndex < 0 || $flowIndex >= count($flowQuestionIds)) {
            return $this->fail('Invalid question index.', 422);
        }

        $questionId = $flowQuestionIds[$flowIndex];
        $question = $this->loadQuestionsByIds($exam, [$questionId], $attempt)->first();
        if (!$question) {
            return $this->fail('Question not found.', 404);
        }

        $attempt->session_answers = $this->normalizeSessionAnswers((array) $request->input('session_answers', $attempt->session_answers ?? []));
        $attempt->session_answer_data = $this->normalizeSessionAnswerData((array) $request->input('session_answer_data', $attempt->session_answer_data ?? []));
        $attempt->revealed_questions = $this->mergeFlowIndices(
            (array) ($attempt->revealed_questions ?? []),
            [$flowIndex],
            $flowQuestionIds,
            $allQuestionIds
        );
        $attempt->committed_questions = $this->mergeFlowIndices(
            (array) ($attempt->committed_questions ?? []),
            [$flowIndex],
            $flowQuestionIds,
            $allQuestionIds
        );
        $attempt->save();

        $formatted = $this->formatQuestion($question);
        $live = $this->liveScoreForAttempt($exam, $attempt, $allQuestionIds, $flowQuestionIds);
        $qResult = $live['question_results'][$questionId] ?? [];

        return $this->ok([
            'correct_option_ids' => $formatted['answer_metadata']['correct_option_ids'],
            'correct_matches' => $formatted['answer_metadata']['correct_matches'],
            'is_correct' => (bool) ($qResult['is_correct'] ?? false),
            'explanation' => $formatted['explanation'],
            'live_score' => [
                'correct' => (int) ($live['correct'] ?? 0),
                'total_questions' => (int) ($live['total_questions'] ?? 0),
                'percentage' => (float) ($live['percentage'] ?? 0),
                'evaluated' => (int) ($live['evaluated'] ?? $live['answered'] ?? 0),
            ],
        ]);
    }

    public function submit(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::NO)
            ->find($attemptId);

        if (!$attempt) {
            return $this->fail('Active exam session not found.', 404);
        }

        $exam = Exam::active()->running()->with('category')->findOrFail($attempt->exam_id);
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $flowQuestions = $this->loadQuestionsByIds($exam, $flowQuestionIds, $attempt);

        $storedAnswers = $this->normalizeSessionAnswers((array) $request->input('session_answers', $attempt->session_answers ?? []));
        $storedAnswerData = $this->normalizeSessionAnswerData((array) $request->input('session_answer_data', $attempt->session_answer_data ?? []));

        $committedFlow = $this->mergeFlowIndices(
            (array) ($attempt->committed_questions ?? []),
            (array) $request->input('committed_flow_indices', []),
            $flowQuestionIds,
            $allQuestionIds
        );
        $committedFlowIndices = $this->globalIndicesToFlowIndices($committedFlow, $flowQuestionIds, $allQuestionIds);

        $score = ExamScoreCalculator::evaluate(
            $flowQuestions,
            $storedAnswers,
            $storedAnswerData,
            $committedFlowIndices,
            (float) ($exam->pass_percentage ?? 82.5)
        );

        DB::transaction(function () use ($attempt, $exam, $flowQuestions, $storedAnswers, $storedAnswerData, $score) {
            ExamAnswer::where('exam_attend_id', $attempt->id)->delete();

            foreach ($flowQuestions as $question) {
                $qId = (string) $question->id;
                $qResult = $score['question_results'][$qId] ?? [];
                $isCorrect = !empty($qResult['is_correct']) ? Status::YES : Status::NO;

                if ($question->question_type === 'drag_drop') {
                    $submitted = $storedAnswerData[$qId] ?? [];
                    if ($submitted) {
                        $row = new ExamAnswer();
                        $row->exam_attend_id = $attempt->id;
                        $row->question_id = $question->id;
                        $row->option_id = 0;
                        $row->answer_data = json_encode($submitted);
                        $row->is_correct = $isCorrect;
                        $row->save();
                    }
                } else {
                    $optIds = (array) ($storedAnswers[$qId] ?? []);
                    foreach ($optIds as $optId) {
                        $row = new ExamAnswer();
                        $row->exam_attend_id = $attempt->id;
                        $row->question_id = $question->id;
                        $row->option_id = (int) $optId;
                        $row->is_correct = $isCorrect;
                        $row->save();
                    }
                }
            }

            $attempt->session_answers = $storedAnswers;
            $attempt->session_answer_data = $storedAnswerData;
            $attempt->is_submit = Status::YES;
            $attempt->status = Status::EXAM_COMPLETED;
            $attempt->end_time = now();
            $attempt->correct_answer = (int) $score['correct'];
            $attempt->pass_percentage = (float) $score['percentage'];
            $attempt->retake_question_ids = null;
            $attempt->save();

            if (!empty($score['passed'])) {
                $exists = GetCertificateUser::where('attend_exam_id', $attempt->id)->exists();
                if (!$exists) {
                    $cert = new GetCertificateUser();
                    $cert->user_id = $attempt->user_id;
                    $cert->attend_exam_id = $attempt->id;
                    $cert->secret = Str::upper(Str::random(12));
                    $cert->status = Status::YES;
                    $cert->save();
                }
            }
        });

        return $this->result($request, $attempt->id);
    }

    public function result(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->with(['exam.category', 'examAnswer', 'certificateUser'])
            ->find($attemptId);

        if (!$attempt || !$attempt->exam) {
            return $this->fail('Exam attempt not found.', 404);
        }

        $exam = $attempt->exam;
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $flowQuestions = $this->loadQuestionsByIds($exam, $flowQuestionIds, $attempt);

        $score = $this->scoreStoredAttempt(
            $flowQuestions,
            $attempt->examAnswer,
            [],
            (float) ($exam->pass_percentage ?? 82.5)
        );

        $settings = array_merge($this->defaultExamSettings(), (array) ($attempt->exam_settings ?? []));

        return $this->ok([
            'attempt_id' => (int) $attempt->id,
            'exam' => $this->formatExam($exam, true),
            'is_submitted' => (int) $attempt->is_submit === Status::YES,
            'end_time_millis' => $attempt->end_time ? \Carbon\Carbon::parse($attempt->end_time)->getTimestampMs() : null,
            'timer_label' => $this->timerLabel($settings, count($flowQuestionIds)),
            'score_result' => $this->formatScoreResult($score),
            'certificate' => $attempt->certificateUser ? $this->formatCertificate($attempt->certificateUser) : null,
        ]);
    }

    public function review(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::YES)
            ->with(['exam.category', 'examAnswer'])
            ->find($attemptId);

        if (!$attempt || !$attempt->exam) {
            return $this->fail('Submitted exam attempt not found.', 404);
        }

        $exam = $attempt->exam;
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $attempt);
        $flowQuestions = $this->loadQuestionsByIds($exam, $flowQuestionIds, $attempt);

        $score = $this->scoreStoredAttempt(
            $flowQuestions,
            $attempt->examAnswer,
            [],
            (float) ($exam->pass_percentage ?? 82.5)
        );

        $formattedScore = $this->formatScoreResult($score);

        return $this->ok([
            'attempt_id' => (int) $attempt->id,
            'exam' => $this->formatExam($exam, true),
            'questions' => $flowQuestions->map(fn ($q) => $this->formatQuestion($q))->values()->all(),
            'results_by_question_id' => $formattedScore['question_results'],
        ]);
    }

    public function retake(Request $request, $attemptId)
    {
        $mode = (string) $request->input('mode', 'all');
        if (!in_array($mode, ['all', 'missed', 'incorrect', 'marked'], true)) {
            return $this->fail('Invalid retake mode.', 422);
        }

        $previous = AttendExam::where('user_id', $request->user()->id)
            ->where('is_submit', Status::YES)
            ->with('examAnswer')
            ->find($attemptId);

        if (!$previous) {
            return $this->fail('Previous attempt not found.', 404);
        }

        $exam = Exam::active()->running()->findOrFail($previous->exam_id);
        $allQuestionIds = $this->orderedQuestionIds($exam);
        $flowQuestionIds = $this->flowQuestionIds($allQuestionIds, $previous);
        $flowQuestions = $this->loadQuestionsByIds($exam, $flowQuestionIds, $previous);

        $targetIds = [];
        if ($mode === 'all') {
            $targetIds = $flowQuestionIds;
        } elseif ($mode === 'marked') {
            $flaggedFlow = $this->globalIndicesToFlowIndices((array) ($previous->flagged_questions ?? []), $flowQuestionIds, $allQuestionIds);
            foreach ($flaggedFlow as $idx) {
                if (isset($flowQuestionIds[$idx])) {
                    $targetIds[] = $flowQuestionIds[$idx];
                }
            }
        } else {
            $score = $this->scoreStoredAttempt($flowQuestions, $previous->examAnswer, [], (float) ($exam->pass_percentage ?? 82.5));
            foreach ($flowQuestionIds as $qId) {
                $res = $score['question_results'][$qId] ?? [];
                $matches = $mode === 'missed'
                    ? empty($res['answered'])
                    : (!empty($res['answered']) && empty($res['is_correct']));
                if ($matches) {
                    $targetIds[] = $qId;
                }
            }
        }

        if (!$targetIds) {
            return $this->fail("There are no {$mode} questions to retake.", 422);
        }

        $settings = array_merge($this->defaultExamSettings(), (array) ($previous->exam_settings ?? []));
        $seconds = $this->timerSeconds((string) ($settings['timer'] ?? 'not_timed'), count($targetIds));

        $newAttempt = new AttendExam();
        $newAttempt->user_id = $request->user()->id;
        $newAttempt->exam_id = $exam->id;
        $newAttempt->start_time = now();
        $newAttempt->remaining_seconds = $seconds;
        $newAttempt->current_question = 0;
        $newAttempt->exam_settings = $settings;
        $newAttempt->question_order = $flowQuestionIds;
        $newAttempt->option_orders = $previous->option_orders;
        $newAttempt->retake_question_ids = $targetIds;
        $newAttempt->save();

        return $this->ok(['attempt_id' => (int) $newAttempt->id], 'Retake session created.', 201);
    }

    public function destroy(Request $request, $attemptId)
    {
        $attempt = AttendExam::where('user_id', $request->user()->id)->find($attemptId);
        if (!$attempt) {
            return $this->fail('Attempt not found.', 404);
        }

        DB::transaction(function () use ($attempt) {
            ExamAnswer::where('exam_attend_id', $attempt->id)->delete();
            GetCertificateUser::where('attend_exam_id', $attempt->id)->delete();
            $attempt->delete();
        });

        return $this->ok(null, 'Exam attempt deleted.');
    }

    private function orderedQuestionIds(Exam $exam): array
    {
        $query = $exam->isAggregate()
            ? Question::query()->whereIn('exam_id', $exam->sourceExamIds())
            : $exam->questions();

        return $query->active()
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
            if (($attendExam->exam_settings['mode'] ?? 'study') !== 'simulation') {
                $savedSet = array_fill_keys($savedOrder, true);
                $savedOrder = array_merge($savedOrder, array_values(array_filter($allQuestionIds, fn ($id) => !isset($savedSet[$id]))));
            }
            $allQuestionIds = $savedOrder;
        }

        $retakeIds = array_map('strval', (array) ($attendExam->retake_question_ids ?? []));
        if (!$retakeIds) {
            return $allQuestionIds;
        }

        $retakeSet = array_fill_keys($retakeIds, true);
        return array_values(array_filter($allQuestionIds, fn ($id) => isset($retakeSet[$id])));
    }

    private function loadQuestionsByIds(Exam $exam, array $ids, AttendExam $attempt): Collection
    {
        if (!$ids) {
            return collect();
        }

        $questions = Question::whereIn('id', $ids)
            ->with(['options' => fn ($q) => $q->active()->orderBy('id')])
            ->get()
            ->keyBy(fn ($q) => (string) $q->id);

        $optionOrders = (array) ($attempt->option_orders ?? []);

        return collect($ids)
            ->map(function ($id) use ($questions, $optionOrders) {
                $q = $questions->get((string) $id);
                if (!$q) {
                    return null;
                }
                $order = (array) ($optionOrders[(string) $id] ?? []);
                if ($order) {
                    $positions = array_flip(array_map('intval', $order));
                    $q->setRelation('options', $q->options->sortBy(fn ($opt) => $positions[(int) $opt->id] ?? PHP_INT_MAX)->values());
                }
                return $q;
            })
            ->filter()
            ->values();
    }

    private function normalizeSessionAnswers(array $raw): array
    {
        $clean = [];
        foreach ($raw as $qId => $val) {
            $arr = is_array($val) ? array_values(array_filter(array_map('intval', $val), fn ($v) => $v > 0)) : ((int) $val > 0 ? [(int) $val] : []);
            if ($arr) {
                $clean[(string) $qId] = count($arr) === 1 ? $arr[0] : $arr;
            }
        }
        return $clean;
    }

    private function normalizeSessionAnswerData(array $raw): array
    {
        $clean = [];
        foreach ($raw as $qId => $map) {
            if (is_array($map) && $map) {
                $clean[(string) $qId] = array_map('strval', $map);
            }
        }
        return $clean;
    }

    private function globalIndicesToFlowIndices(array $globalIndices, array $flowQuestionIds, array $allQuestionIds): array
    {
        $flowLookup = array_flip($flowQuestionIds);
        $mapped = [];
        foreach ($globalIndices as $gIdx) {
            $qId = $allQuestionIds[(int) $gIdx] ?? null;
            if ($qId !== null && isset($flowLookup[$qId])) {
                $mapped[] = (int) $flowLookup[$qId];
            }
        }
        return array_values(array_unique($mapped));
    }

    private function mergeFlowIndices(array $existingGlobalIndices, array $incomingFlowIndices, array $flowQuestionIds, array $allQuestionIds): array
    {
        $globalLookup = array_flip($allQuestionIds);
        $flowSet = array_flip($flowQuestionIds);

        $kept = [];
        foreach ($existingGlobalIndices as $gIdx) {
            $qId = $allQuestionIds[(int) $gIdx] ?? null;
            if ($qId !== null && !isset($flowSet[$qId])) {
                $kept[] = (int) $gIdx;
            }
        }

        foreach ($incomingFlowIndices as $fIdx) {
            $qId = $flowQuestionIds[(int) $fIdx] ?? null;
            if ($qId !== null && isset($globalLookup[$qId])) {
                $kept[] = (int) $globalLookup[$qId];
            }
        }

        $kept = array_values(array_unique($kept));
        sort($kept);
        return $kept;
    }

    private function liveScoreForAttempt(Exam $exam, AttendExam $attempt, array $allQuestionIds, array $flowQuestionIds, ?Collection $loadedQuestions = null): array
    {
        $questions = $loadedQuestions ?? $this->loadQuestionsByIds($exam, $flowQuestionIds, $attempt);
        $committedFlowIndices = $this->globalIndicesToFlowIndices((array) ($attempt->committed_questions ?? []), $flowQuestionIds, $allQuestionIds);

        return ExamScoreCalculator::evaluate(
            $questions,
            (array) ($attempt->session_answers ?? []),
            (array) ($attempt->session_answer_data ?? []),
            $committedFlowIndices,
            (float) ($exam->pass_percentage ?? 82.5)
        );
    }

    private function scoreStoredAttempt(Collection $questions, Collection $answerRows, array $committedIndices, float $passingThreshold): array
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

        return ExamScoreCalculator::evaluate($questions, $answers, $answerData, $committedIndices, $passingThreshold);
    }

    private function dragDropFeedbackForQuestion(Question $question, array $submitted): array
    {
        $correctMatches = array_map('strval', (array) ($question->answer_data['correct_matches'] ?? []));
        $targets = array_values(array_map('strval', (array) ($question->answer_data['targets'] ?? array_keys($correctMatches))));
        $slotResults = [];
        $allCorrect = count($targets) > 0;

        foreach ($targets as $target) {
            $expected = $correctMatches[$target] ?? null;
            $actual = isset($submitted[$target]) ? trim((string) $submitted[$target]) : '';
            if ($actual === '') {
                $allCorrect = false;
                $slotResults[$target] = ['status' => 'missing', 'correct_item' => $expected, 'submitted_item' => null];
            } elseif ($expected !== null && $actual === $expected) {
                $slotResults[$target] = ['status' => 'correct', 'correct_item' => $expected, 'submitted_item' => $actual];
            } else {
                $allCorrect = false;
                $slotResults[$target] = ['status' => 'wrong', 'correct_item' => $expected, 'submitted_item' => $actual];
            }
        }

        return [
            'is_correct' => $allCorrect,
            'correct_matches' => (object) $correctMatches,
            'slot_results' => (object) $slotResults,
        ];
    }
}
