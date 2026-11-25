@php
    $latestExamContent = getContent('latest_exam.content', true);
    $availableExams = App\Models\Exam::active()->upcoming()
        ->withCount(['questions as active_questions_count' => fn ($query) => $query->active()])
        ->limit(3)->get();
@endphp

@if (!blank($availableExams))
    <section class="exam-section my-120">
        <div class="container">
            <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
                <h2 class="section-heading__title" data-highlight-start="{{ $latestExamContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $latestExamContent?->data_values?->highlight_end ?? 3 }}">
                    {{ __($latestExamContent?->data_values?->heading ?? '') }}
                </h2>
                <p class="section-heading__desc">
                    {{ __($latestExamContent?->data_values?->short_description ?? '') }}
                </p>
            </div>
            <div class="row gy-4">
                @include('Template::partials.exam_card', ['exams' => $availableExams])
            </div>
        </div>
    </section>
@endif
