<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model {
    use GlobalStatus;

    protected $casts = [
        'monthly_price'      => 'decimal:2',
        'yearly_price'       => 'decimal:2',
        'allowed_categories' => 'array',
        'allowed_exams'      => 'array',
        'feature_flags'      => 'array',
        'simulation_mode'    => 'boolean',
        'is_free'            => 'boolean',
    ];

    public function subscriptions() {
        return $this->hasMany(Subscription::class);
    }

    public function accessCategoryIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->allowed_categories ?? [])),
            fn ($id) => $id > 0
        )));
    }

    public function accessExamIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->allowed_exams ?? [])),
            fn ($id) => $id > 0
        )));
    }

    public function allowsCategory(?int $categoryId): bool
    {
        return $categoryId !== null && in_array((int) $categoryId, $this->accessCategoryIds(), true);
    }

    public function allowsExam(?Exam $exam): bool
    {
        if (!$exam || !$this->allowsCategory((int) $exam->category_id)) {
            return false;
        }

        return in_array((int) $exam->id, $this->accessExamIds(), true);
    }

    public function allowsSimulation(): bool
    {
        return (bool) $this->simulation_mode;
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) data_get($this->feature_flags ?? [], $feature, false);
    }

    public function accessCategories()
    {
        return Category::query()->whereIn('id', $this->accessCategoryIds())->orderBy('name')->get();
    }

    public function accessExams()
    {
        return Exam::query()->with('category')->whereIn('id', $this->accessExamIds())->orderBy('sort_order')->orderBy('id')->get();
    }

    public static function free(): ?self
    {
        return static::active()->where('is_free', true)->orderBy('id')->first();
    }
}
