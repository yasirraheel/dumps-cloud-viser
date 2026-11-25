<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Question extends Model {
    use GlobalStatus;

    protected $casts = [
        'answer_data' => 'array',
        'sort_order'  => 'integer',
    ];

    public function options() {
        return $this->hasMany(Option::class);
    }

    public function result() {
        return $this->belongsTo(Option::class, 'result_option_id', 'id');
    }

    public function explanationForDisplayedOptionOrder(): string
    {
        $explanation = (string) ($this->explanation ?? '');
        $displayedOptions = $this->relationLoaded('options')
            ? $this->getRelation('options')->values()
            : collect();

        if ($explanation === '' || $displayedOptions->count() < 2) {
            return $explanation;
        }

        $displayIndexById = [];
        foreach ($displayedOptions as $index => $option) {
            $displayIndexById[(int) $option->id] = $index;
        }

        $letterMap = [];
        foreach ($displayedOptions->sortBy('id')->values() as $originalIndex => $option) {
            $id = (int) $option->id;
            if (isset($displayIndexById[$id])) {
                $letterMap[chr(65 + $originalIndex)] = chr(65 + $displayIndexById[$id]);
            }
        }

        return preg_replace_callback(
            '/(\\bOption\\s+)([A-Z])\\b/u',
            static fn ($match) => $match[1] . ($letterMap[$match[2]] ?? $match[2]),
            $explanation
        ) ?? $explanation;
    }
}
