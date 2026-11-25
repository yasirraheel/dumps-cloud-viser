<?php

namespace App\Models;

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

    public function scopeUpcoming($query) {
        return $query->where('start_at', '>', now());
    }

    public function scopeRunning($query) {
        $query->where('start_at', '<=', now())->where('result_published_at', '>=', now());
    }
}
