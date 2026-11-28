@php
    $counterContent = getContent('counter.content', true);
    $counterElement = getContent('counter.element', orderById: true);
    $visitorSeed = session()->getId() ?: request()->userAgent() . '|' . request()->ip();
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
                    $rawValue = trim((string) ($counterData?->count_value ?? '0'));
                    $numericValue = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '', $rawValue));
                    $variation = min(max((float) ($counterData?->variation ?? 3), 0), 10);
                    $symbol = trim((string) ($counterData?->count_symbol ?? ''));
                    $displayValue = $numericValue;

                    if ($numericValue > 0 && $variation > 0) {
                        $counterHash = sprintf('%u', crc32($visitorSeed . '|' . $counter->id));
                        $variationFactor = (($counterHash % 2001) / 1000) - 1;
                        $displayValue += round($numericValue * ($variation / 100) * $variationFactor);
                    }

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
