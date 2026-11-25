<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ExamScoreCalculator
{
    public static function evaluate(iterable $questions, array $answers, array $answerData, array $committedQuestionIndices = [], float $passingThreshold = 82.5): array
    {
        $questions = collect($questions)->values();
        $committed = array_fill_keys(array_map('intval', $committedQuestionIndices), true);
        $questionResults = [];
        $answeredCount = 0;
        $correctCount = 0;

        foreach ($questions as $index => $question) {
            $questionId = (string) $question->id;
            $type = $question->question_type ?? 'single_choice';
            $answer = $answers[$questionId] ?? $answers[$question->id] ?? null;
            $submittedOptionIds = self::normalizeOptionIds($answer);
            $data = self::toArray($question->answer_data ?? []);
            $userData = self::toArray($answerData[$questionId] ?? $answerData[$question->id] ?? []);
            $isAnswered = false;
            $isCorrect = false;
            $correctOptionIds = [];
            $correctMatches = [];

            if ($type === 'drag_drop') {
                $correctMatches = self::toArray($data['correctMatches'] ?? []);
                $isAnswered = count($userData) > 0;
                $isCorrect = !empty($committed[$index]) && self::dragDropIsCorrect($data, $userData);
            } else {
                $correctOptionIds = self::correctOptionIds($question, $data, $type);
                $isAnswered = count($submittedOptionIds) > 0;
                $submittedSorted = $submittedOptionIds;
                $correctSorted = $correctOptionIds;
                sort($submittedSorted);
                sort($correctSorted);

                if ($type === 'multiple_selection') {
                    $isCorrect = $isAnswered && $submittedSorted === $correctSorted;
                } else {
                    $isCorrect = count($submittedOptionIds) === 1
                        && count($correctOptionIds) > 0
                        && $submittedOptionIds[0] === $correctOptionIds[0];
                }
            }

            if ($isAnswered) {
                $answeredCount++;
            }
            if ($isCorrect) {
                $correctCount++;
            }

            $questionResults[$questionId] = [
                'answered' => $isAnswered,
                'is_correct' => $isCorrect,
                'selected_option_ids' => $submittedOptionIds,
                'correct_option_ids' => $correctOptionIds,
                'submitted_matches' => $userData,
                'correct_matches' => $correctMatches,
            ];
        }

        $totalQuestions = $questions->count();
        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0.0;
        $incorrectCount = $answeredCount - $correctCount;
        $unansweredCount = $totalQuestions - $answeredCount;

        return [
            'total_questions' => $totalQuestions,
            'answered' => $answeredCount,
            'correct' => $correctCount,
            'incorrect' => $incorrectCount,
            'unanswered' => $unansweredCount,
            'percentage' => $percentage,
            'passing_percentage' => $passingThreshold,
            'passed' => $percentage >= $passingThreshold,
            'question_results' => $questionResults,
        ];
    }

    public static function decodeAnswerData(mixed $value): array
    {
        return self::toArray($value);
    }

    public static function requiredSelectionCount($question): int
    {
        $data = self::toArray($question->answer_data ?? []);
        $optionIds = self::normalizeOptionIds($data['correct_option_ids'] ?? []);
        if ($optionIds) {
            return count($optionIds);
        }

        $indices = array_values(array_unique(array_map('intval', (array) ($data['correct_indices'] ?? []))));
        if ($indices) {
            return count($indices);
        }

        $title = (string) ($question->title ?? '');
        if (preg_match('/\\b(?:choose|select|which)\\s+(one|two|three|four|five|six|seven|eight|nine|ten|\\d+)\\b/i', $title, $matches)) {
            $counts = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];
            return max(1, (int) ($counts[strtolower($matches[1])] ?? $matches[1]));
        }

        return 2;
    }

    private static function correctOptionIds($question, array $data, string $type): array
    {
        $ids = self::normalizeOptionIds($data['correct_option_ids'] ?? []);

        if ($type !== 'multiple_selection' && $question->result_option_id) {
            return [(int) $question->result_option_id];
        }

        if ($ids) {
            return $ids;
        }

        $indices = array_map('intval', (array) ($data['correct_indices'] ?? []));
        if ($indices) {
            $optionIds = $question->relationLoaded('options')
                ? $question->options->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                : $question->options()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            foreach ($indices as $index) {
                if (isset($optionIds[$index])) {
                    $ids[] = $optionIds[$index];
                }
            }
        }

        if (!$ids && $type !== 'multiple_selection' && $question->result_option_id) {
            $ids[] = (int) $question->result_option_id;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    private static function dragDropIsCorrect(array $data, array $submitted): bool
    {
        $correct = self::toArray($data['correctMatches'] ?? []);
        $targets = array_values((array) ($data['targets'] ?? array_keys($correct)));
        if (!$targets || !$correct) {
            return false;
        }

        $expectedByGroup = [];
        foreach ($targets as $target) {
            $target = (string) $target;
            $item = $correct[$target] ?? null;
            if (!is_scalar($item) || (string) $item === '') {
                return false;
            }
            $group = self::targetGroup($target);
            $expectedByGroup[$group][] = (string) $item;
        }

        foreach ($targets as $target) {
            $target = (string) $target;
            $item = $submitted[$target] ?? null;
            if (!is_scalar($item) || (string) $item === '') {
                return false;
            }
            $group = self::targetGroup($target);
            $matchIndex = array_search((string) $item, $expectedByGroup[$group] ?? [], true);
            if ($matchIndex === false) {
                return false;
            }
            array_splice($expectedByGroup[$group], $matchIndex, 1);
        }

        return true;
    }

    private static function targetGroup(string $target): string
    {
        $target = trim($target);
        if (preg_match('/^step\b/i', $target) || preg_match('/command\b/i', $target)) {
            return $target;
        }
        if (preg_match('/^(.+?)\s*\d+$/', $target, $matches)) {
            return trim($matches[1]);
        }
        return $target;
    }

    private static function normalizeOptionIds(mixed $value): array
    {
        $values = is_array($value) ? $value : (($value === null || $value === '') ? [] : [$value]);
        $ids = array_values(array_unique(array_filter(array_map('intval', $values), fn ($id) => $id > 0)));
        sort($ids);
        return $ids;
    }

    private static function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof Collection) {
            return $value->all();
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
