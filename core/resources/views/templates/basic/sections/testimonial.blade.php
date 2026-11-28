@php
    $testimonialContent = getContent('testimonial.content', true);
    $testimonials = getContent('testimonial.element');
@endphp

<section class="testimonials my-120">
    <div class="container">
        <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
            <h2 class="section-heading__title" data-highlight-start="{{ $testimonialContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $testimonialContent?->data_values?->highlight_end ?? 3 }}">
                {{ __($testimonialContent?->data_values?->heading ?? '') }}
            </h2>
            <p class="section-heading__desc">
                {{ __($testimonialContent?->data_values?->short_details ?? '') }}
            </p>
        </div>
    </div>
    <div class="testimonial-slider wow fadeInDown" data-wow-delay="0.2s">
        @foreach ($testimonials as $testimonial)
            @php
                $testimonialName = trim((string) ($testimonial?->data_values?->name ?? ''));
                $testimonialInitial = $testimonialName !== '' ? mb_strtoupper(mb_substr($testimonialName, 0, 1)) : '?';
            @endphp
            <div class="testimonial">
                <div class="testimonial-wrapper">
                    <div class="testimonial-author">
                        <div class="testimonial-avatar" aria-hidden="true">
                            {{ $testimonialInitial }}
                        </div>
                        <div class="testimonial-person">
                            <div class="testimonial-name">{{ __($testimonial?->data_values?->name ?? '') }}</div>
                            <div class="testimonial-job"> {{ __($testimonial?->data_values?->designation ?? '') }}</div>
                        </div>
                    </div>
                    <div class="testimonial-content">
                        <div class="testimonial-quotemark">
                            “
                        </div>
                        <div class="testimonial-quote">
                            {{ __($testimonial->data_values?->review ?? '') }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
