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

    public static function ensureCategoryAll(int $categoryId): ?self
    {
        $category = Category::find($categoryId);
        if (!$category) {
            return null;
        }

        $sourceExams = static::query()
            ->where('category_id', $categoryId)
            ->where('exam_type', '!=', 'category_all')
            ->where('status', Status::ENABLE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'image']);

        if ($sourceExams->isEmpty()) {
            return null;
        }

        $aggregate = static::query()
            ->where('category_id', $categoryId)
            ->where('exam_type', 'category_all')
            ->first();

        if (!$aggregate) {
            $aggregate = new static();
            $aggregate->category_id = $categoryId;
            $aggregate->title = 'All Questions';
            $aggregate->description = 'All active questions from this category in one practice bank.';
            $aggregate->instructions = 'Choose Study mode for the complete bank or Simulation mode for a timed random selection.';
            $aggregate->slug = slug($category->slug . '-all-questions');
            $aggregate->duration = 90;
            $aggregate->pass_percentage = 84;
            $aggregate->subjects = [$category->name];
            $aggregate->exam_type = 'category_all';
            $aggregate->randomize_questions = true;
            $aggregate->randomize_options = false;
            $aggregate->show_answers = true;
            $aggregate->status = Status::ENABLE;
            $aggregate->result_published = Status::NO;
            $aggregate->start_at = now();
            $aggregate->result_published_at = now()->addYears(10);
            $aggregate->sort_order = ((int) static::query()->max('sort_order')) + 1;
        }

        $aggregate->question_quantity = Question::query()
            ->whereIn('exam_id', $sourceExams->pluck('id'))
            ->active()
            ->count();
        $aggregate->image = $sourceExams->first()->image;
        $aggregate->save();

        return $aggregate;
    }

    public function scopeUpcoming($query) {
        return $query->where('start_at', '>', now());
    }

    public function scopeRunning($query) {
        $query->where('start_at', '<=', now())->where('result_published_at', '>=', now());
    }
}
