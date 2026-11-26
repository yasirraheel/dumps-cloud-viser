<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('categories')
            ->where('status', 1)
            ->orderBy('id')
            ->get()
            ->each(function ($category) use ($now) {
                $sourceExams = DB::table('exams')
                    ->where('category_id', $category->id)
                    ->where('exam_type', '!=', 'category_all')
                    ->where('status', 1)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'image']);

                if ($sourceExams->isEmpty()) {
                    return;
                }

                $existing = DB::table('exams')
                    ->where('category_id', $category->id)
                    ->where('exam_type', 'category_all')
                    ->first();

                $sourceIds = $sourceExams->pluck('id')->all();
                $questionCount = DB::table('questions')
                    ->whereIn('exam_id', $sourceIds)
                    ->where('status', 1)
                    ->count();

                $data = [
                    'category_id' => $category->id,
                    'title' => 'All Questions',
                    'description' => 'All active questions from this category in one practice bank.',
                    'instructions' => 'Choose Study mode for the complete bank or Simulation mode for a timed random selection.',
                    'slug' => Str::slug($category->slug . '-all-questions'),
                    'question_quantity' => $questionCount,
                    'duration' => 90,
                    'pass_percentage' => 84,
                    'subjects' => json_encode([$category->name]),
                    'exam_type' => 'category_all',
                    'randomize_questions' => true,
                    'randomize_options' => false,
                    'show_answers' => true,
                    'status' => 1,
                    'result_published' => 0,
                    'start_at' => $now,
                    'result_published_at' => $now->copy()->addYears(10),
                    'image' => $sourceExams->first()->image,
                    'sort_order' => ((int) DB::table('exams')->max('sort_order')) + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($existing) {
                    DB::table('exams')->where('id', $existing->id)->update([
                        'question_quantity' => $questionCount,
                        'image' => $data['image'],
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('exams')->insert($data);
                }
            });
    }

    public function down(): void
    {
        DB::table('exams')->where('exam_type', 'category_all')->delete();
    }
};
