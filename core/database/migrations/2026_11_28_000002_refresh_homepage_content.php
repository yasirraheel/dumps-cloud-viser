<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $this->updateFirst('banner.content', function (array $data): array {
            return array_merge($data, [
                'heading' => 'Practice smarter for your next exam',
                'highlight_start' => '5',
                'highlight_end' => '6',
                'short_description' => 'Work through exam banks, timed practice, answer review, and progress tracking in one focused place.',
                'review_rating' => '4.8',
                'rating_text' => 'Trusted by active learners',
            ]);
        }, $now);

        $this->updateFirst('contact_us.content', function (array $data): array {
            return array_merge($data, [
                'contact_number' => '0336 7663074',
                'contact_number_description' => 'WhatsApp or call us and we will be happy to help.',
                'contact_address' => 'Street 7, Model Town, Bahawalpur, Punjab, Pakistan',
                'contact_address_description' => 'Our support team is based in Bahawalpur, Punjab, Pakistan.',
                'map_link' => 'https://www.google.com/maps?q=Model+Town+Bahawalpur+Punjab+Pakistan&output=embed',
            ]);
        }, $now);

        $this->updateFirst('footer.content', function (array $data): array {
            return array_merge($data, [
                'description' => 'Practise with focused exam banks, timed sessions, and clear answer review. Build confidence for your next certification exam.',
            ]);
        }, $now);

        $counterDefaults = [
            'Exams Available' => ['count_symbol' => ''],
            'Active Learners' => ['count_symbol' => ''],
            'Exam Categories' => ['count_symbol' => ''],
            'Learner Success Rate' => ['count_value' => '99', 'count_symbol' => '%', 'variation' => '0'],
        ];

        DB::table('frontends')->where('data_keys', 'counter.element')->get(['id', 'data_values'])->each(function ($row) use ($counterDefaults, $now) {
            $data = json_decode($row->data_values, true);
            $title = is_array($data) ? (string) ($data['title'] ?? '') : '';
            if (!is_array($data) || !isset($counterDefaults[$title])) {
                return;
            }

            DB::table('frontends')->where('id', $row->id)->update([
                'data_values' => json_encode(array_merge($data, $counterDefaults[$title]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ]);
        });

        $this->updateFirst('testimonial.content', function (array $data): array {
            return array_merge($data, [
                'heading' => 'Feedback from learners',
                'short_details' => 'A few words from people using exam banks, timed sessions, and answer review in their regular study routine.',
            ]);
        }, $now);

        $testimonials = [
            ['name' => 'Muhammad Asif', 'designation' => 'Network Engineer', 'review' => 'I use the CCNA banks after work. The explanations are short and clear, so I can see where I am making the same mistake again.', 'rating' => '5'],
            ['name' => 'Ayesha Khan', 'designation' => 'IT Support Specialist', 'review' => 'The timed session helped me get used to the exam pace. I also like that my old attempts stay in history for a quick review.', 'rating' => '5'],
            ['name' => 'Rahul Sharma', 'designation' => 'Junior Network Engineer', 'review' => 'I started with one bank and then moved to answer review. It is simple to use and I do not feel lost while practising.', 'rating' => '5'],
            ['name' => 'Nusrat Jahan', 'designation' => 'Network Support Associate', 'review' => 'Some questions were tricky, but the explanations helped me understand the topic instead of only showing a score.', 'rating' => '5'],
            ['name' => 'Hamza Ahmed', 'designation' => 'Systems Administrator', 'review' => 'I normally do a small session at night. Progress and marked questions make it easy to continue the next day.', 'rating' => '5'],
            ['name' => 'Priya Mehta', 'designation' => 'IT Student', 'review' => 'The CCNA practice questions are useful for checking the basics. I still use my notes, but this gives me a good way to test myself.', 'rating' => '5'],
        ];

        DB::table('frontends')->where('data_keys', 'testimonial.element')->orderBy('id')->get(['id', 'data_values'])->values()->each(function ($row, $index) use ($testimonials, $now) {
            if (!isset($testimonials[$index])) {
                return;
            }

            $data = json_decode($row->data_values, true);
            if (!is_array($data)) {
                return;
            }

            DB::table('frontends')->where('id', $row->id)->update([
                'data_values' => json_encode(array_merge($data, $testimonials[$index]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ]);
        });

        $this->updateFirst('faq.content', function (array $data): array {
            return array_merge($data, [
                'heading' => 'FAQ',
                'subheading' => 'Questions about exam practice',
                'short_description' => 'Find quick answers about exam banks, timed sessions, plans, results, and certificates.',
                'button_text' => 'Need help?',
                'button_url' => '/contact',
            ]);
        }, $now);

        $faqs = [
            ['question' => 'What can I do on Dumps.Cloud?', 'answer' => 'Dumps.Cloud gives you organised exam banks, timed practice sessions, answer explanations, and exam history so you can prepare and track your progress in one place.'],
            ['question' => 'Which certification exams are available?', 'answer' => 'The Exams page always shows the active categories and banks currently available. The catalogue starts with Cisco CCNA 200-301, with more tracks added as they are ready.'],
            ['question' => 'Can I try an exam bank before subscribing?', 'answer' => 'Yes. Your plan controls which categories, banks, and questions you can access. The free plan shows the practice content included in that plan, while paid plans unlock more access.'],
            ['question' => 'Can I review my answers and past attempts?', 'answer' => 'Yes. After a session, you can review your answers, explanations, score, and status. Completed attempts are also kept in Exam History.'],
            ['question' => 'How is my result calculated?', 'answer' => 'Your result is calculated from the questions allowed in that session, not from locked questions in the full bank. The result page shows your score, percentage, and pass or fail status.'],
        ];

        DB::table('frontends')->where('data_keys', 'faq.element')->orderBy('id')->get(['id', 'data_values'])->values()->each(function ($row, $index) use ($faqs, $now) {
            if (!isset($faqs[$index])) {
                return;
            }

            $data = json_decode($row->data_values, true);
            if (!is_array($data)) {
                return;
            }

            DB::table('frontends')->where('id', $row->id)->update([
                'data_values' => json_encode(array_merge($data, $faqs[$index]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ]);
        });

        $this->updateFirst('blog.content', function (array $data): array {
            return array_merge($data, [
                'heading' => 'Exam Prep Notes',
                'highlight_start' => '2',
                'highlight_end' => '2',
                'short_description' => 'Simple study notes and practical tips for the certification exams available on Dumps.Cloud.',
            ]);
        }, $now);

        $blogs = [
            'how-ai-is-transforming-modern-exam-management-system' => [
                'title' => 'How to Use an Exam Bank Without Losing Your Study Plan',
                'description' => '<div class="mb-5"><p>Start with one exam bank and set a small target for each session. Review the questions you miss, write down the topic, and come back to it instead of rushing through the whole bank at once. A simple routine makes practice more useful.</p></div>',
            ],
            'top-benefits-of-using-automated-proctoring-for-online-exams' => [
                'title' => 'How to Make Timed Practice More Useful',
                'description' => '<div class="mb-5"><p>A timer is useful when you treat it like a practice tool, not a race. Keep a steady pace, mark questions you want to revisit, and check your result after the session. This helps you find whether time or understanding is the real problem.</p></div>',
            ],
            'why-educational-institutions-are-shifting-to-ai-powered-assessments' => [
                'title' => 'A Simple Way to Review Missed Questions',
                'description' => '<div class="mb-5"><p>Answer review works best when you look at the reason behind a mistake. Read the explanation, compare it with your own thinking, and note the topic in a short list. Use that list to choose what to practise next.</p></div>',
            ],
            'the-future-of-exam-creation-ai-generated-questions-explained' => [
                'title' => 'Preparing for Cisco CCNA 200-301',
                'description' => '<div class="mb-5"><p>The CCNA 200-301 covers network fundamentals, access, IP connectivity, security, and automation. Use focused question banks to check what you remember, then spend more time on the topics where your answers are not consistent.</p></div>',
            ],
            'how-to-improve-exam-integrity-with-smart-monitoring-tools' => [
                'title' => 'How to Keep Your Practice on Track',
                'description' => '<div class="mb-5"><p>Keep each session easy to measure. Look at your score, time spent, missed questions, and marked items after finishing. Exam History gives you a simple record so you can decide what to revise before the next session.</p></div>',
            ],
            'a-complete-guide-to-building-a-secure-online-examination-environment' => [
                'title' => 'What to Do After a Practice Exam',
                'description' => '<div class="mb-5"><p>Do not stop at the percentage. Review the questions you missed, read the explanations, and choose one topic for your next study block. Checking the pass mark alongside your score also gives you a clearer picture of your progress.</p></div>',
            ],
        ];

        foreach ($blogs as $slug => $content) {
            $data = DB::table('frontends')->where('data_keys', 'blog.element')->where('slug', $slug)->value('data_values');
            if ($data === null) {
                continue;
            }

            $data = json_decode($data, true);
            if (!is_array($data)) {
                continue;
            }

            DB::table('frontends')->where('data_keys', 'blog.element')->where('slug', $slug)->update([
                'data_values' => json_encode(array_merge($data, $content), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ]);
        }
    }

    private function updateFirst(string $dataKey, callable $transform, $now): void
    {
        $row = DB::table('frontends')->where('data_keys', $dataKey)->orderBy('id')->first(['id', 'data_values']);
        if (!$row) {
            return;
        }

        $data = json_decode($row->data_values, true);
        if (!is_array($data)) {
            return;
        }

        DB::table('frontends')->where('id', $row->id)->update([
            'data_values' => json_encode($transform($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Homepage copy is editable content; it is intentionally not overwritten on rollback.
    }
};
