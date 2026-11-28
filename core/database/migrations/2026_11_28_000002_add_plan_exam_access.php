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
            $table->json('allowed_categories')->nullable()->after('yearly_exam');
            $table->json('allowed_exams')->nullable()->after('allowed_categories');
            $table->json('feature_flags')->nullable()->after('allowed_exams');
            $table->boolean('simulation_mode')->default(false)->after('feature_flags');
            $table->boolean('is_free')->default(false)->after('simulation_mode');
        });

        $activeCategories = DB::table('categories')
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name']);
        $allCategoryIds = $activeCategories->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $ccnaCategory = $activeCategories->first(fn ($category) => str_contains(strtolower((string) $category->name), 'ccna'))
            ?: $activeCategories->first();
        $ccnaCategoryId = $ccnaCategory?->id ? (int) $ccnaCategory->id : null;

        $ccnaExams = $ccnaCategoryId
            ? DB::table('exams')->where('category_id', $ccnaCategoryId)->where('status', 1)->orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'exam_type'])
            : collect();
        $regularCcnaExams = $ccnaExams->filter(fn ($exam) => $exam->exam_type !== 'category_all')->values();

        $findExamIds = function (array $needles) use ($regularCcnaExams): array {
            return $regularCcnaExams
                ->filter(function ($exam) use ($needles) {
                    $title = strtolower((string) $exam->title);
                    return collect($needles)->contains(fn ($needle) => str_contains($title, strtolower($needle)));
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        };

        $bankA = $findExamIds(['bank a']);
        $bankAB = $findExamIds(['bank a', 'bank b']);
        $bankABCDDrag = $findExamIds(['bank a', 'bank b', 'bank c', 'bank d', 'drag']);
        $fallbackIds = $regularCcnaExams->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $bankA = $bankA ?: array_slice($fallbackIds, 0, 1);
        $bankAB = $bankAB ?: array_slice($fallbackIds, 0, 2);
        $bankABCDDrag = $bankABCDDrag ?: $fallbackIds;
        $allExamIds = DB::table('exams')->where('status', 1)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $plans = [
            [
                'name' => 'Free',
                'short_description' => 'Start with one exam bank and core practice tools.',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'monthly_exam' => 3,
                'yearly_exam' => 12,
                'allowed_categories' => $ccnaCategoryId ? [$ccnaCategoryId] : [],
                'allowed_exams' => $bankA,
                'feature_flags' => ['answer_explanations' => true, 'progress_tracking' => true, 'certificate' => false],
                'simulation_mode' => false,
                'is_free' => true,
            ],
            [
                'name' => 'Starter',
                'short_description' => 'Practice the core CCNA banks with a simple monthly plan.',
                'monthly_price' => 10,
                'yearly_price' => 90,
                'monthly_exam' => 10,
                'yearly_exam' => 120,
                'allowed_categories' => $ccnaCategoryId ? [$ccnaCategoryId] : [],
                'allowed_exams' => $bankAB,
                'feature_flags' => ['answer_explanations' => true, 'progress_tracking' => true, 'certificate' => true],
                'simulation_mode' => false,
                'is_free' => false,
            ],
            [
                'name' => 'Professional',
                'short_description' => 'Unlock the complete CCNA practice set with simulation mode.',
                'monthly_price' => 20,
                'yearly_price' => 180,
                'monthly_exam' => 30,
                'yearly_exam' => 360,
                'allowed_categories' => $ccnaCategoryId ? [$ccnaCategoryId] : [],
                'allowed_exams' => $bankABCDDrag,
                'feature_flags' => ['answer_explanations' => true, 'progress_tracking' => true, 'certificate' => true],
                'simulation_mode' => true,
                'is_free' => false,
            ],
            [
                'name' => 'Complete Access',
                'short_description' => 'Access every available bank and the full practice experience.',
                'monthly_price' => 30,
                'yearly_price' => 270,
                'monthly_exam' => 100,
                'yearly_exam' => 1200,
                'allowed_categories' => $allCategoryIds,
                'allowed_exams' => $allExamIds,
                'feature_flags' => ['answer_explanations' => true, 'progress_tracking' => true, 'certificate' => true],
                'simulation_mode' => true,
                'is_free' => false,
            ],
        ];

        foreach ($plans as $plan) {
            $existing = DB::table('plans')->where('name', $plan['name'])->first();
            $payload = [
                'short_description' => $plan['short_description'],
                'monthly_price' => $plan['monthly_price'],
                'yearly_price' => $plan['yearly_price'],
                'monthly_exam' => $plan['monthly_exam'],
                'yearly_exam' => $plan['yearly_exam'],
                'allowed_categories' => json_encode($plan['allowed_categories']),
                'allowed_exams' => json_encode($plan['allowed_exams']),
                'feature_flags' => json_encode($plan['feature_flags']),
                'simulation_mode' => $plan['simulation_mode'],
                'is_free' => $plan['is_free'],
                'status' => 1,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('plans')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('plans')->insert(array_merge($payload, [
                    'name' => $plan['name'],
                    'created_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Plan access data is intentionally retained if this migration is rolled back.
    }
};
