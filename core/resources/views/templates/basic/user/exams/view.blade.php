@extends('Template::layouts.frontend')

@section('content')
    @php $isCompleted = (int) $attendExam->status === Status::EXAM_COMPLETED; @endphp

    <section class="exam-section exam-result-page my-60">
        <div class="container">
            <div class="result-page-heading">
                <div>
                    <span class="result-kicker">{{ __($exam->category?->name ?? 'Exam') }}</span>
                    <h1>{{ __($exam->title) }}</h1>
                    <p>{{ $totalQuestions }} @lang('questions') <span aria-hidden="true">·</span> {{ $attemptTimerLabel }}</p>
                </div>
                <a class="btn btn--dark exam-return-link" href="{{ route('user.exam.history') }}"><i class="las la-arrow-left" aria-hidden="true"></i><span>@lang('Exam History')</span></a>
            </div>

            @if ($isCompleted && $scoreData)
                <section class="result-summary {{ $scoreData['passed'] ? 'result-summary--pass' : 'result-summary--fail' }}" aria-labelledby="resultTitle">
                    <div class="result-summary-header">
                        <div>
                            <span class="result-kicker">@lang('Exam result')</span>
                            <h2 id="resultTitle">{{ $scoreData['passed'] ? __('You passed') : __('Exam complete') }}</h2>
                            <p>@lang('Submitted') {{ showDateTime($attendExam->end_time) }}</p>
                        </div>
                        <span class="result-status {{ $scoreData['passed'] ? 'result-status--pass' : 'result-status--fail' }}">
                            {{ $scoreData['passed'] ? __('PASS') : __('FAIL') }}
                            <i class="las {{ $scoreData['passed'] ? 'la-check' : 'la-times' }}"></i>
                        </span>
                    </div>

                    <div class="result-metrics">
                        <div class="result-metric result-metric--primary">
                            <span class="result-metric-label">@lang('Questions correct')</span>
                            <strong>{{ $scoreData['correct'] }} <small>/ {{ $scoreData['total_questions'] }}</small></strong>
                            <span class="result-metric-note">{{ $scoreData['incorrect'] }} @lang('incorrect') · {{ $scoreData['unanswered'] }} @lang('unanswered')</span>
                        </div>
                        <div class="result-metric">
                            <span class="result-metric-label">@lang('Result percentage')</span>
                            <strong>{{ number_format($scoreData['percentage'], 2) }}%</strong>
                        </div>
                    </div>

                    <div class="result-score-track" role="progressbar" aria-label="@lang('Exam score')" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, max(0, $scoreData['percentage'])) }}">
                        <span class="result-score-fill" style="width: {{ min(100, max(0, $scoreData['percentage'])) }}%"></span>
                        <span class="result-pass-marker" style="left: {{ min(100, max(0, $scoreData['passing_percentage'])) }}%" aria-hidden="true"></span>
                    </div>
                    <div class="result-track-legend"><span>@lang('Your score') <strong>{{ number_format($scoreData['percentage'], 2) }}%</strong></span><span>@lang('Pass mark') <strong>{{ number_format($scoreData['passing_percentage'], 1) }}%</strong></span></div>

                    <div class="result-actions">
                        <a href="{{ route('user.exam.review', ['id' => $attendExam->id, 'index' => 0]) }}" class="btn btn--base result-review-button"><i class="las la-search" aria-hidden="true"></i><span>@lang('Review exam')</span></a>
                        <a href="{{ route('user.exam.history') }}" class="btn btn--dark">@lang('Back to history')</a>
                    </div>
                </section>
            @elseif ((int) $attendExam->status === Status::EXAM_INITIATE)
                <div class="result-notice">
                    <i class="las la-pause-circle"></i>
                    <div><strong>@lang('This exam is still in progress')</strong><p>@lang('Your saved answers and remaining time are ready when you resume.')</p></div>
                    <a href="{{ route('user.exam.resume', $attendExam->id) }}" class="btn btn--base">@lang('Resume exam') <i class="las la-arrow-right"></i></a>
                </div>
            @else
                <div class="result-notice result-notice--pending">
                    <i class="las la-hourglass-half"></i>
                    <div><strong>@lang('Result is being prepared')</strong><p>@lang('Please return to your exam history shortly.')</p></div>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('style')
    <style>
        .exam-result-page{padding-top:26px}.result-page-heading,.result-summary-header{align-items:center;display:flex;gap:18px;justify-content:space-between}.result-page-heading{border-bottom:1px solid hsl(var(--border-color));margin-bottom:22px;padding-bottom:18px}.result-page-heading h1{font-size:clamp(1.4rem,2.6vw,2rem);margin:5px 0}.result-page-heading p,.result-summary-header p{color:hsl(var(--text-color));margin:0}.result-kicker{color:hsl(var(--base));font-size:.78rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.result-summary{background:hsl(var(--section-bg));border:1px solid hsl(var(--border-color));border-left:4px solid hsl(var(--base));border-radius:12px;margin-bottom:24px;padding:24px}.result-summary--pass{border-left-color:hsl(var(--success))}.result-summary--fail{border-left-color:hsl(var(--danger))}.result-summary-header h2{font-size:clamp(1.25rem,2vw,1.65rem);margin:4px 0}.result-status{align-items:center;border:1px solid;border-radius:8px;display:inline-flex;font-size:.9rem;font-weight:800;gap:6px;padding:8px 13px;white-space:nowrap}.result-status--pass{background:hsl(var(--success)/.1);border-color:hsl(var(--success)/.45);color:hsl(var(--success))}.result-status--fail{background:hsl(var(--danger)/.1);border-color:hsl(var(--danger)/.45);color:hsl(var(--danger))}.result-metrics{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr));margin:22px 0}.result-metric{background:hsl(var(--body-bg)/.55);border:1px solid hsl(var(--border-color));border-radius:9px;display:flex;flex-direction:column;gap:5px;min-width:0;padding:15px}.result-metric-label,.result-metric-note{color:hsl(var(--text-color));font-size:.82rem}.result-metric strong{font-size:clamp(1.25rem,2.1vw,1.8rem);line-height:1.15}.result-metric strong small{color:hsl(var(--text-color));font-size:.7em}.result-metric--primary strong{color:hsl(var(--base))}.result-score-track{background:hsl(var(--border-color));border-radius:999px;height:12px;position:relative}.result-score-fill{background:hsl(var(--base));border-radius:inherit;display:block;height:100%;max-width:100%;position:relative;z-index:1}.result-summary--pass .result-score-fill{background:hsl(var(--success))}.result-summary--fail .result-score-fill{background:hsl(var(--danger))}.result-pass-marker{border-left:2px solid hsl(var(--white));height:20px;position:absolute;top:-4px;transform:translateX(-1px);z-index:2}.result-track-legend{color:hsl(var(--text-color));display:flex;font-size:.82rem;justify-content:space-between;margin-top:8px}.result-track-legend strong{color:hsl(var(--white))}.result-actions{align-items:center;border-top:1px solid hsl(var(--border-color));display:flex;flex-wrap:wrap;gap:10px;margin-top:20px;padding-top:18px}.result-actions .result-review-button{align-items:center;display:inline-flex;gap:9px;justify-content:center}.result-review-button i{display:block;flex:0 0 auto;font-size:1rem;line-height:1}.result-review-button span{display:block;line-height:1.2}.result-notice{align-items:center;border:1px solid hsl(var(--border-color));border-radius:10px;display:flex;gap:14px;padding:18px}.result-notice>i{color:hsl(var(--base));font-size:1.5rem}.result-notice p{color:hsl(var(--text-color));margin:3px 0 0}.result-notice .btn{margin-left:auto;white-space:nowrap}.result-notice--pending{background:hsl(var(--warning)/.06);border-color:hsl(var(--warning)/.25)}
        @media(max-width:991px){.result-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.exam-result-page{padding-top:12px}.result-page-heading,.result-summary-header{align-items:flex-start;flex-direction:column}.result-page-heading>.btn{align-self:flex-start}.result-summary{padding:17px}.result-metrics{gap:8px}.result-metric{padding:11px}.result-metric-label,.result-metric-note{font-size:.75rem}.result-actions{align-items:stretch;flex-direction:column}.result-actions .btn{justify-content:center}.result-notice{align-items:flex-start;flex-wrap:wrap}.result-notice .btn{margin-left:0}}
    </style>
    <style>
        .exam-return-link{align-items:center;display:inline-flex;gap:10px;justify-content:center;min-height:44px;padding:10px 16px;background:hsl(var(--section-bg));border:1px solid hsl(var(--border-color));border-radius:8px;color:hsl(var(--white));line-height:1.2;text-decoration:none;white-space:nowrap;transition:background-color .18s ease,border-color .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}.exam-return-link i{display:block;flex:0 0 auto;font-size:1rem;line-height:1;margin:0}.exam-return-link span{display:block;line-height:1.2}.exam-return-link:hover,.exam-return-link:focus-visible{background:hsl(var(--base)/.1);border-color:hsl(var(--base));color:hsl(var(--base));box-shadow:0 5px 14px hsl(var(--black)/.2);transform:translateY(-1px)}.exam-return-link:focus-visible{outline:2px solid hsl(var(--base));outline-offset:2px}.exam-return-link:active{transform:translateY(0)}
        @media(prefers-reduced-motion:reduce){.exam-return-link{transition:none}}
    </style>
@endpush
