<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('exam_question_limits')->nullable()->after('allowed_exams');
        });

        $activeExams = DB::table('exams')
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'category_id', 'exam_type']);
        $questionCounts = DB::table('questions')
            ->where('status', 1)
            ->select('exam_id', DB::raw('COUNT(*) as total'))
            ->groupBy('exam_id')
            ->pluck('total', 'exam_id')
            ->map(fn ($total) => (int) $total);

        $availableQuestions = [];
        foreach ($activeExams as $exam) {
            if ($exam->exam_type === 'category_all') {
                $count = $activeExams
                    ->where('category_id', $exam->category_id)
                    ->where('exam_type', '!=', 'category_all')
                    ->sum(fn ($sourceExam) => (int) ($questionCounts[$sourceExam->id] ?? 0));
            } else {
                $count = (int) ($questionCounts[$exam->id] ?? 0);
            }
            $availableQuestions[(string) $exam->id] = $count;
        }

        foreach (DB::table('plans')->get(['id', 'allowed_exams']) as $plan) {
            $allowedExamIds = json_decode((string) $plan->allowed_exams, true);
            $allowedExamIds = is_array($allowedExamIds) ? $allowedExamIds : [];
            $limits = [];

            foreach ($allowedExamIds as $examId) {
                $examId = (string) (int) $examId;
                $available = (int) ($availableQuestions[$examId] ?? 0);
                if ($available > 0) {
                    $limits[$examId] = $available;
                }
            }

            DB::table('plans')->where('id', $plan->id)->update([
                'exam_question_limits' => json_encode($limits),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('exam_question_limits');
        });
    }
};
