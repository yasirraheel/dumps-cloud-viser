<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $counterDefaults = [
        'Exams Generated' => [
            'title' => 'Exams Available',
            'count_value' => '2400',
            'count_symbol' => '+',
            'variation' => '3',
        ],
        'Active Educators' => [
            'title' => 'Active Learners',
            'count_value' => '320',
            'count_symbol' => '+',
            'variation' => '4',
        ],
        'Subjects Supported' => [
            'title' => 'Exam Categories',
            'count_value' => '18',
            'count_symbol' => '+',
            'variation' => '5',
        ],
        'Success Rate Boost' => [
            'title' => 'Learner Success Rate',
            'count_value' => '92',
            'count_symbol' => '%',
            'variation' => '1',
        ],
    ];

    public function up(): void
    {
        $rows = DB::table('frontends')
            ->where('data_keys', 'counter.element')
            ->get(['id', 'data_values']);

        foreach ($rows as $row) {
            $data = json_decode($row->data_values, true);
            if (!is_array($data)) {
                continue;
            }

            $title = (string) ($data['title'] ?? '');

            if (!isset($this->counterDefaults[$title])) {
                continue;
            }

            $default = $this->counterDefaults[$title];
            $oldValues = [
                'Exams Generated' => '45000',
                'Active Educators' => '12000',
                'Subjects Supported' => '150',
                'Success Rate Boost' => '98',
            ];

            if ((string) ($data['count_value'] ?? '') !== $oldValues[$title]) {
                continue;
            }

            DB::table('frontends')->where('id', $row->id)->update([
                'data_values' => json_encode(array_merge($data, $default), JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        $restore = [
            'Exams Available' => ['title' => 'Exams Generated', 'count_value' => '45000', 'count_symbol' => '+'],
            'Active Learners' => ['title' => 'Active Educators', 'count_value' => '12000', 'count_symbol' => '+'],
            'Exam Categories' => ['title' => 'Subjects Supported', 'count_value' => '150', 'count_symbol' => '+'],
            'Learner Success Rate' => ['title' => 'Success Rate Boost', 'count_value' => '98', 'count_symbol' => '%'],
        ];

        $rows = DB::table('frontends')
            ->where('data_keys', 'counter.element')
            ->get(['id', 'data_values']);

        foreach ($rows as $row) {
            $data = json_decode($row->data_values, true);
            if (!is_array($data)) {
                continue;
            }

            $title = (string) ($data['title'] ?? '');

            if (!isset($restore[$title])) {
                continue;
            }

            $data = array_merge($data, $restore[$title]);
            unset($data['variation']);

            DB::table('frontends')->where('id', $row->id)->update([
                'data_values' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
};
