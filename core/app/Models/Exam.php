<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model {
    use GlobalStatus;
    protected $casts = [
        'subjects' => 'array',
        'randomize_questions' => 'boolean',
        'randomize_options' => 'boolean',
        'show_answers' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category() {
        return $this->belongsTo(Category::class);
    }
    public function questions() {
        return $this->hasMany(Question::class);
    }
    public function attendExam() {
        return $this->hasMany(AttendExam::class);
    }

    public function isAggregate(): bool
    {
        return $this->exam_type === 'category_all';
    }

    public function sourceExamIds(): array
    {
        if (!$this->isAggregate()) {
            return [(int) $this->id];
        }

        return static::query()
            ->where('category_id', $this->category_id)
            ->where('id', '!=', $this->id)
            ->where('exam_type', '!=', 'category_all')
            ->where('status', Status::ENABLE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function activeQuestionCount(): int
    {
        return Question::query()
            ->whereIn('exam_id', $this->sourceExamIds())
            ->active()
            ->count();
    }

    public function scopeUpcoming($query) {
        return $query->where('start_at', '>', now());
    }

    public function scopeRunning($query) {
        $query->where('start_at', '<=', now())->where('result_published_at', '>=', now());
    }
}
