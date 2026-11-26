<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Status;
use App\Lib\ExamScoreCalculator;
use App\Models\AttendExam;
use App\Models\Exam;
use App\Models\GetCertificateUser;
use App\Models\Question;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

trait ApiV1Trait
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function fail(string $message, int $status = 422, array $errors = []): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
        ];
        if ($errors) {
            $payload['errors'] = $errors;
        }
        return response()->json($payload, $status);
    }

    protected function defaultExamSettings(): array
    {
        return [
            'mode' => 'study',
            'question_order' => 'original',
            'randomize_options' => false,
            'show_score_live' => true,
            'include_show_answer' => true,
            'inline_feedback' => true,
            'timer' => 'not_timed',
            'simulation_percent' => null,
            'simulation_count' => null,
        ];
    }

    protected function formatUserProfile(User $user): array
    {
        $user->loadMissing('plan');
        $expiresMillis = $user->expired_date
            ? Carbon::parse($user->expired_date)->endOfDay()->getTimestampMs()
            : 0;

        $prefs = array_merge($this->defaultExamSettings(), (array) ($user->exam_preferences ?? []));

        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'firstname' => (string) ($user->firstname ?? ''),
            'lastname' => (string) ($user->lastname ?? ''),
            'full_name' => trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) ?: (string) $user->username,
            'email' => (string) $user->email,
            'mobile' => $user->mobile ? (string) $user->mobile : null,
            'active_plan_id' => (int) ($user->plan_id ?? 0),
            'active_plan_name' => $user->plan?->name ? (string) $user->plan->name : 'No Active Plan',
            'exam_credits_remaining' => (int) ($user->exam_limit ?? 0),
            'plan_expires_at_millis' => $expiresMillis,
            'preferred_settings' => $this->formatExamSettings($prefs),
        ];
    }

    protected function formatExamSettings(array $settings): array
    {
        $merged = array_merge($this->defaultExamSettings(), $settings);
        return [
            'mode' => (string) $merged['mode'],
            'question_order' => (string) $merged['question_order'],
            'randomize_options' => (bool) $merged['randomize_options'],
            'show_score_live' => (bool) $merged['show_score_live'],
            'include_show_answer' => (bool) $merged['include_show_answer'],
            'inline_feedback' => (bool) $merged['inline_feedback'],
            'timer' => (string) $merged['timer'],
            'simulation_percent' => isset($merged['simulation_percent']) ? (int) $merged['simulation_percent'] : null,
            'simulation_count' => isset($merged['simulation_count']) ? (int) $merged['simulation_count'] : null,
        ];
    }

    protected function formatExam(Exam $exam, bool $isBooked = false): array
    {
        $exam->loadMissing('category');
        $slug = (string) ($exam->slug ?? '');
        $imageKey = match (true) {
            str_contains($slug, 'bank-a') => 'img_exam_bank_a',
            str_contains($slug, 'bank-b') => 'img_exam_bank_b',
            str_contains($slug, 'bank-c') => 'img_exam_bank_c',
            str_contains($slug, 'bank-d') => 'img_exam_bank_d',
            str_contains($slug, 'drag') => 'img_exam_drag_drop',
            default => 'img_exam_all_questions',
        };

        $imageUrl = $exam->image
            ? url(getFilePath('exam') . '/' . $exam->image)
            : null;

        return [
            'id' => (int) $exam->id,
            'category_id' => (int) $exam->category_id,
            'category_name' => (string) ($exam->category?->name ?? ''),
            'category_slug' => (string) ($exam->category?->slug ?? ''),
            'title' => (string) $exam->title,
            'slug' => $slug,
            'description' => (string) ($exam->description ?? ''),
            'instructions' => (string) ($exam->instructions ?? ''),
            'exam_type' => (string) ($exam->exam_type ?? 'practice'),
            'duration_minutes' => (int) ($exam->duration ?? 60),
            'pass_percentage' => (float) ($exam->pass_percentage ?? 82.5),
            'subjects' => array_values((array) ($exam->subjects ?? [])),
            'image_key' => $imageKey,
            'image_url' => $imageUrl,
            'question_count' => $exam->activeQuestionCount(),
            'sort_order' => (int) ($exam->sort_order ?? 0),
            'is_booked' => $isBooked,
            'booking_status' => $isBooked ? 'booked' : 'available',
        ];
    }

    protected function formatQuestion(Question $question): array
    {
        $options = $question->relationLoaded('options')
            ? $question->options->values()
            : collect();

        $type = (string) ($question->question_type ?? 'single_choice');
        $answerData = (array) ($question->answer_data ?? []);
        $correctOptionIds = [];
        $correctIndices = [];

        if ($type === 'multiple_choice') {
            $indices = array_map('intval', (array) ($answerData['correct_indices'] ?? []));
            $correctIndices = $indices;
            foreach ($indices as $idx) {
                if (isset($options[$idx])) {
                    $correctOptionIds[] = (int) $options[$idx]->id;
                }
            }
        } elseif ($type === 'single_choice' && $question->result_option_id) {
            $correctOptionIds = [(int) $question->result_option_id];
            foreach ($options as $idx => $opt) {
                if ((int) $opt->id === (int) $question->result_option_id) {
                    $correctIndices[] = (int) $idx;
                    break;
                }
            }
        }

        return [
            'id' => (int) $question->id,
            'exam_id' => (int) $question->exam_id,
            'title' => (string) $question->title,
            'question_type' => $type,
            'explanation' => $question->explanationForDisplayedOptionOrder(),
            'cli_snippet' => $question->cli_snippet ?: null,
            'exhibit_image_url' => $this->resolvePublicMediaUrl($question->exhibit_image),
            'original_source_image_url' => $this->resolvePublicMediaUrl($question->original_source_image),
            'answer_metadata' => [
                'correct_indices' => array_values($correctIndices),
                'correct_option_ids' => array_values($correctOptionIds),
                'items' => array_values(array_map('strval', (array) ($answerData['items'] ?? []))),
                'targets' => array_values(array_map('strval', (array) ($answerData['targets'] ?? []))),
                'correct_matches' => (object) array_map('strval', (array) ($answerData['correct_matches'] ?? [])),
            ],
            'result_option_id' => $question->result_option_id ? (int) $question->result_option_id : null,
            'sort_order' => (int) ($question->sort_order ?? 0),
            'options' => $options->map(fn ($opt) => [
                'id' => (int) $opt->id,
                'question_id' => (int) $opt->question_id,
                'title' => (string) $opt->title,
            ])->values()->all(),
        ];
    }

    protected function resolvePublicMediaUrl(?string $path): ?string
    {
        $trimmed = trim((string) $path);
        if ($trimmed === '') {
            return null;
        }
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }
        return url(ltrim($trimmed, '/'));
    }

    protected function formatCertificate(GetCertificateUser $cert): array
    {
        $attempt = AttendExam::with('exam.category')->find($cert->attend_exam_id);
        $exam = $attempt?->exam;
        $user = User::find($cert->user_id);

        return [
            'id' => (int) $cert->id,
            'attempt_id' => (int) $cert->attend_exam_id,
            'secret' => (string) $cert->secret,
            'holder_name' => trim(($user?->firstname ?? '') . ' ' . ($user?->lastname ?? '')) ?: (string) ($user?->username ?? ''),
            'exam_title' => (string) ($exam?->title ?? 'Exam'),
            'category_name' => (string) ($exam?->category?->name ?? ''),
            'score_percentage' => (float) ($attempt?->pass_percentage ?? 0.0),
            'issued_at_millis' => $cert->created_at ? $cert->created_at->getTimestampMs() : 0,
        ];
    }

    protected function formatScoreResult(array $score): array
    {
        $formattedQuestions = [];
        foreach ((array) ($score['question_results'] ?? []) as $qId => $res) {
            $formattedQuestions[(string) $qId] = [
                'answered' => (bool) ($res['answered'] ?? false),
                'is_correct' => (bool) ($res['is_correct'] ?? false),
                'selected_option_ids' => array_values(array_map('intval', (array) ($res['selected_option_ids'] ?? []))),
                'correct_option_ids' => array_values(array_map('intval', (array) ($res['correct_option_ids'] ?? []))),
                'submitted_matches' => (object) array_map('strval', (array) ($res['submitted_matches'] ?? [])),
                'correct_matches' => (object) array_map('strval', (array) ($res['correct_matches'] ?? [])),
            ];
        }

        return [
            'total_questions' => (int) ($score['total_questions'] ?? 0),
            'answered' => (int) ($score['answered'] ?? 0),
            'correct' => (int) ($score['correct'] ?? 0),
            'incorrect' => (int) ($score['incorrect'] ?? 0),
            'unanswered' => (int) ($score['unanswered'] ?? 0),
            'percentage' => (float) ($score['percentage'] ?? 0.0),
            'passing_percentage' => (float) ($score['passing_percentage'] ?? 0.0),
            'passed' => (bool) ($score['passed'] ?? false),
            'question_results' => (object) $formattedQuestions,
        ];
    }

    protected function timerSeconds(string $timer, int $questionCount): ?int
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

    protected function timerLabel(array $settings, int $questionCount): string
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
}
