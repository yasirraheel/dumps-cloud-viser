@php
    $counterContent = getContent('counter.content', true);
    $counterElement = getContent('counter.element', orderById: true);
    $liveStats = [
        'exams available'   => \App\Models\Exam::active()->running()->count(),
        'exams generated'   => \App\Models\Exam::active()->running()->count(),
        'active learners'   => \App\Models\User::active()->count(),
        'active educators'  => \App\Models\User::active()->count(),
        'exam categories'   => \App\Models\Category::active()->whereHas('exams', function ($query) {
            $query->active()->running();
        })->count(),
        'subjects supported' => \App\Models\Category::active()->whereHas('exams', function ($query) {
            $query->active()->running();
        })->count(),
        'learner success rate' => 99,
        'success rate boost'   => 99,
    ];
@endphp




<section class="statistics-section my-120">
    <div class="statistics-section-shape">
        <img src="{{ asset(activeTemplate(true) . 'images/thumbs/globe.svg') }}" alt="img" />
    </div>
    <div class="statistics-section-shape left-side">
        <img src="{{ asset(activeTemplate(true) . 'images/thumbs/globe.svg') }}" alt="img" />
    </div>
    <div class="container">
        <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
            <h2 class="section-heading__title text-white" data-highlight-start="{{ $counterContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $counterContent?->data_values?->highlight_end ?? 3 }}">
                {{ __($counterContent?->data_values?->heading ?? '') }}
            </h2>
            <p class="section-heading__desc text-white">
                {{ __($counterContent?->data_values?->short_description ?? '') }}
            </p>
        </div>

        <div class="row gy-4 wow fadeInDown" data-wow-delay="0.2s">
            @foreach ($counterElement as $counter)
                @php
                    $counterData = $counter?->data_values;
                    $counterTitle = strtolower(trim((string) ($counterData?->title ?? '')));
                    $isLiveStat = array_key_exists($counterTitle, $liveStats);
                    $rawValue = trim((string) ($counterData?->count_value ?? '0'));
                    $numericValue = $isLiveStat
                        ? (float) $liveStats[$counterTitle]
                        : (float) preg_replace('/[^0-9.]/', '', str_replace(',', '', $rawValue));
                    $symbol = $isLiveStat
                        ? ($counterTitle === 'learner success rate' || $counterTitle === 'success rate boost' ? '%' : '')
                        : trim((string) ($counterData?->count_symbol ?? ''));
                    $displayValue = $numericValue;

                    $displayValue = max(str_contains($symbol, '%') ? 0 : 1, $displayValue);
                    if (str_contains($symbol, '%')) {
                        $displayValue = min(100, $displayValue);
                    }
                    $displayValue = number_format($displayValue, 0, '.', '');
                @endphp
                <div class="col-lg-3 col-sm-6">
                    <div class="statistics-item">
                        <p class="statistics-item-title">{{ __($counterData?->title ?? '') }}</p>
                        <h3 class="statistics-item-count">
                            <span class="odometer" data-odometer-final="{{ $displayValue }}">0</span>{{ $symbol }}
                        </h3>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
