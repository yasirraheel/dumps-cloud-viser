<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $articles = [
            [
                'date' => '2026-11-24 10:00:00',
                'title' => 'What to Do Before Your Next Practice Session',
                'description' => '<div class="mb-5"><p>Choose one exam bank, set a clear target, and keep your notes nearby before you start. A short plan helps you focus on the questions that matter instead of clicking through a session without a purpose.</p></div>',
            ],
            [
                'date' => '2026-11-18 10:00:00',
                'title' => 'Using Exam History to Plan Your Next Session',
                'description' => '<div class="mb-5"><p>Exam History gives you a useful record of your attempts. Check your score, missed questions, and marked items, then use that information to choose the topic you should practise next.</p></div>',
            ],
            [
                'date' => '2026-11-12 10:00:00',
                'title' => 'A Practical Study Routine for Cisco CCNA 200-301',
                'description' => '<div class="mb-5"><p>Split your CCNA practice into small, regular sessions. Move through network fundamentals, access, IP connectivity, security, and automation, and return to the areas where your answers are not consistent.</p></div>',
            ],
            [
                'date' => '2026-11-05 10:00:00',
                'title' => 'How to Review Questions You Missed',
                'description' => '<div class="mb-5"><p>When an answer is wrong, read the explanation and write down the reason for the mistake. This small step makes review more useful and helps you avoid repeating the same error in the next session.</p></div>',
            ],
            [
                'date' => '2026-10-28 10:00:00',
                'title' => 'How to Use a Timer During Exam Practice',
                'description' => '<div class="mb-5"><p>Timed practice is about finding a steady pace. Answer what you know, mark anything that needs another look, and review the result afterwards so you know whether time or understanding needs more work.</p></div>',
            ],
            [
                'date' => '2026-10-21 10:00:00',
                'title' => 'Choosing the Right Exam Bank for Your Goal',
                'description' => '<div class="mb-5"><p>Start with the category and bank that match the certification you are preparing for. Focused practice is easier to manage, and you can use answer review to decide when you are ready to move to another bank.</p></div>',
            ],
        ];

        DB::table('frontends')
            ->where('data_keys', 'blog.element')
            ->orderByDesc('id')
            ->get(['id', 'data_values'])
            ->values()
            ->each(function ($row, $index) use ($articles, $now) {
                if (!isset($articles[$index])) {
                    return;
                }

                $data = json_decode($row->data_values, true);
                if (!is_array($data)) {
                    return;
                }

                $article = $articles[$index];
                DB::table('frontends')->where('id', $row->id)->update([
                    'data_values' => json_encode(array_merge($data, [
                        'title' => $article['title'],
                        'description' => $article['description'],
                    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $article['date'],
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        // Blog copy and dates are editable content; do not overwrite them on rollback.
    }
};
