@extends('Template::layouts.frontend')

@section('content')
    @php
        $optionLetters = range('A', 'Z');
        $questionType = $question->question_type ?? 'single_choice';
        $questionState = ($questionResult['is_correct'] ?? false) ? 'correct' : (($questionResult['answered'] ?? false) ? 'incorrect' : 'missed');
    @endphp

    <section class="exam-section exam-review-page my-60">
        <div class="container">
            <header class="review-header">
                <div class="review-header-top">
                    <a href="{{ route('user.exam.view', $attendExam->id) }}" class="btn btn--dark exam-return-link"><i class="las la-arrow-left" aria-hidden="true"></i><span>@lang('Score report')</span></a>
                    <span class="review-mode-badge"><i class="las la-search" aria-hidden="true"></i><span>@lang('Read-only review')</span></span>
                </div>
                <div class="review-title-row">
                    <div>
                        <span class="review-kicker">{{ __($exam->category?->name ?? 'Exam') }} · @lang('Question') {{ $index + 1 }} @lang('of') {{ $questionCount }}</span>
                        <h1>{{ __($exam->title) }}</h1>
                    </div>
                    <span class="review-answer-state review-answer-state--{{ $questionState }}">
                        @if ($questionState === 'correct') @lang('Correct')
                        @elseif ($questionState === 'incorrect') @lang('Incorrect')
                        @else @lang('Unanswered') @endif
                    </span>
                </div>
            </header>

            <div class="review-layout">
                <main class="review-main">
                    <article class="review-question-card">
                        <div class="review-question-meta">
                            <span class="review-question-number">{{ $index + 1 }}</span>
                            <span>@lang('Question') {{ $index + 1 }}</span>
                            <span class="review-type-badge">{{ ucwords(str_replace('_', ' ', $questionType)) }}</span>
                        </div>
                        <h2 class="review-question-title">{{ __($question->title) }}</h2>

                        @if ($question->exhibit_image)
                            <div class="exam-exhibit"><img src="{{ asset($question->exhibit_image) }}" alt="@lang('Question exhibit')"></div>
                        @endif
                        @if ($question->cli_snippet)
                            <pre class="exam-cli-snippet"><code>{{ $question->cli_snippet }}</code></pre>
                        @endif

                        @if ($questionType === 'drag_drop')
                            @php
                                $submittedMatches = $questionResult['submitted_matches'] ?? [];
                                $correctMatches = $questionResult['correct_matches'] ?? [];
                            @endphp
                            <div class="review-match-list">
                                @forelse ($correctMatches as $target => $correctItem)
                                    <div class="review-match-row">
                                        <strong>{{ $target }}</strong>
                                        <span><small>@lang('Your match')</small>{{ $submittedMatches[$target] ?? __('No answer') }}</span>
                                        <span class="review-correct-match"><small>@lang('Correct match')</small>{{ $correctItem }}</span>
                                    </div>
                                @empty
                                    <p class="review-empty">@lang('No matching data is available for this question.')</p>
                                @endforelse
                            </div>
                        @else
                            @php $correctOptionIds = $questionResult['correct_option_ids'] ?? []; @endphp
                            <div class="review-options">
                                @foreach ($question->options as $option)
                                    @php
                                        $optionTitle = preg_replace('/^\s*[A-Z](?:[\.\)\:\-])\s*/i', '', (string) $option->title);
                                        $displayOptionTitle = $optionTitle;
                                        $isCliOption = preg_match('/^\s*(?:[\w.-]+(?:\([^)]*\))?[>#]|enable\b|line\s+(?:con(?:sole)?|vty)\b|interface\b|router\b|ip\s+(?:access-list|route|address|default-gateway)\b|switchport\b|crypto\b|hostname\b|username\b|service\b|no\s+\S)/i', $optionTitle)
                                            || preg_match('/\bgateway of last resort\b|\b[A-Z](?:\s+(?:E[12]|IA|EX))?\*?\s+\d{1,3}(?:\.\d{1,3}){3}\/\d+\b/i', $optionTitle);
                                        if ($isCliOption) {
                                            $displayOptionTitle = preg_replace('/\s+(?=(?:gateway of last resort\b|[A-Z](?:\s+(?:E[12]|IA|EX))?\*?\s+\d{1,3}(?:\.\d{1,3}){3}\/\d+\b))/i', "\n", trim($displayOptionTitle));
                                            $displayOptionTitle = preg_replace('/\s+(?=[\w.-]+(?:\([^)]*\))?[>#])/', "\n", $displayOptionTitle);
                                            $displayOptionTitle = preg_replace('/\s+(?=(?:line\s+(?:con(?:sole)?|vty)\b|interface\s+\S+|router\s+\S+))/', "\n", $displayOptionTitle);
                                            $displayOptionTitle = preg_replace('/\s+(?=(?:password\s+\S+|login\b|transport\s+input\b|exec-timeout\b|access-class\b|logging\s+synchronous\b|authorization\b|privilege\s+level\b))/i', "\n  ", $displayOptionTitle);
                                        }
                                        $isSelected = in_array((int) $option->id, $selectedOptionIds, true);
                                        $isCorrectOption = in_array((int) $option->id, $correctOptionIds, true);
                                        $optionState = $isCorrectOption ? 'correct' : ($isSelected ? 'wrong' : 'neutral');
                                    @endphp
                                    <div class="review-option review-option--{{ $optionState }}">
                                        <span class="review-option-letter">{{ $optionLetters[$loop->index] ?? $loop->iteration }}</span>
                                        <span class="review-option-text">{{ __($displayOptionTitle) }}</span>
                                        <span class="review-option-note">
                                            @if ($isCorrectOption) @lang('Correct answer')
                                            @elseif ($isSelected) @lang('Your answer') @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <section class="review-explanation">
                            <h3><i class="las la-lightbulb"></i> @lang('Explanation')</h3>
                            <div>{!! $question->explanation ?: '<p>' . __('No explanation has been added yet.') . '</p>' !!}</div>
                        </section>
                    </article>

                    <nav class="review-footer" aria-label="@lang('Question navigation')">
                        @if ($index > 0)
                            <a class="btn btn--dark" data-review-navigation href="{{ route('user.exam.review', ['id' => $attendExam->id, 'index' => $index - 1]) }}"><i class="las la-arrow-left"></i> @lang('Previous')</a>
                        @else
                            <span></span>
                        @endif
                        <span class="review-footer-count">{{ $index + 1 }} / {{ $questionCount }}</span>
                        @if ($index < $questionCount - 1)
                            <a class="btn btn--base" data-review-navigation href="{{ route('user.exam.review', ['id' => $attendExam->id, 'index' => $index + 1]) }}">@lang('Next') <i class="las la-arrow-right"></i></a>
                        @else
                            <a class="btn btn--base" href="{{ route('user.exam.view', $attendExam->id) }}">@lang('Finish review') <i class="las la-check"></i></a>
                        @endif
                    </nav>
                </main>

                <aside class="review-sidebar">
                    <div class="review-question-nav">
                        <h2>@lang('Questions')</h2>
                        <div class="review-question-grid">
                            @for ($questionIndex = 0; $questionIndex < $questionCount; $questionIndex++)
                                <a class="review-question-link {{ $questionIndex === $index ? 'is-current' : '' }}" data-review-navigation href="{{ route('user.exam.review', ['id' => $attendExam->id, 'index' => $questionIndex]) }}" aria-label="@lang('Review question') {{ $questionIndex + 1 }}">{{ $questionIndex + 1 }}</a>
                            @endfor
                        </div>
                        <p>@lang('Choose a question to review its answer and explanation.')</p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection

@push('style')
    <style>
        .exam-review-page{padding-top:24px}.review-header{border-bottom:1px solid hsl(var(--border-color));margin-bottom:20px;padding-bottom:16px}.review-header-top,.review-title-row,.review-question-meta,.review-footer{align-items:center;display:flex;gap:12px}.review-header-top,.review-title-row,.review-footer{justify-content:space-between}.review-header-top{margin-bottom:16px}.review-mode-badge,.review-type-badge{background:hsl(var(--base)/.1);border:1px solid hsl(var(--base)/.3);border-radius:999px;color:hsl(var(--base));font-size:.8rem;font-weight:700;padding:6px 10px}.review-kicker{color:hsl(var(--base));font-size:.8rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.review-title-row h1{font-size:clamp(1.25rem,2.3vw,1.8rem);margin:4px 0 0}.review-answer-state{border:1px solid;border-radius:999px;font-size:.78rem;font-weight:800;padding:6px 10px;white-space:nowrap}.review-answer-state--correct{background:hsl(var(--success)/.1);border-color:hsl(var(--success)/.4);color:hsl(var(--success))}.review-answer-state--incorrect,.review-answer-state--missed{background:hsl(var(--danger)/.08);border-color:hsl(var(--danger)/.35);color:hsl(var(--danger))}.review-layout{align-items:start;display:grid;gap:20px;grid-template-columns:minmax(0,1fr) 230px}.review-question-card,.review-question-nav{background:hsl(var(--section-bg));border:1px solid hsl(var(--border-color));border-radius:11px;padding:20px}.review-question-meta{color:hsl(var(--text-color));flex-wrap:wrap;font-size:.84rem}.review-question-number{align-items:center;background:hsl(var(--base));border-radius:6px;color:hsl(var(--white));display:inline-flex;font-weight:800;height:30px;justify-content:center;width:30px}.review-question-title{font-size:clamp(1.15rem,2vw,1.45rem);line-height:1.5;margin:15px 0 20px}.review-options{display:grid;gap:10px}.review-option{align-items:center;background:hsl(var(--body-bg)/.35);border:1px solid hsl(var(--border-color));border-radius:8px;display:flex;gap:12px;line-height:1.5;padding:12px 14px}.review-option--correct{background:hsl(var(--success)/.08);border-color:hsl(var(--success)/.6)}.review-option--wrong{background:hsl(var(--danger)/.08);border-color:hsl(var(--danger)/.55)}.review-option-letter{align-items:center;background:hsl(var(--base)/.12);border-radius:5px;color:hsl(var(--base));display:flex;flex:0 0 28px;font-weight:800;height:28px;justify-content:center}.review-option--correct .review-option-letter{background:hsl(var(--success)/.15);color:hsl(var(--success))}.review-option-text{flex:1;overflow-wrap:anywhere}.review-option-note{color:hsl(var(--text-color));font-size:.76rem;font-weight:700;margin-left:auto;white-space:nowrap}.review-option--correct .review-option-note{color:hsl(var(--success))}.review-option--wrong .review-option-note{color:hsl(var(--danger))}.review-match-list{display:grid;gap:9px}.review-match-row{background:hsl(var(--body-bg)/.4);border:1px solid hsl(var(--border-color));border-radius:8px;display:grid;gap:10px;grid-template-columns:minmax(140px,1fr) minmax(0,1fr) minmax(0,1fr);padding:12px}.review-match-row>strong{align-self:center}.review-match-row>span{display:flex;flex-direction:column;gap:4px;overflow-wrap:anywhere}.review-match-row small{color:hsl(var(--text-color));font-size:.74rem}.review-correct-match{color:hsl(var(--success))}.review-empty{color:hsl(var(--text-color));margin:0}.review-explanation{background:hsl(var(--base)/.06);border-left:3px solid hsl(var(--base));border-radius:7px;color:hsl(var(--text-color));margin-top:20px;padding:14px 16px}.review-explanation h3{color:hsl(var(--base));font-size:1rem;margin:0 0 8px}.review-explanation p:last-child{margin-bottom:0}.review-explanation img{height:auto;max-width:100%}.review-footer{background:hsl(var(--section-bg));border:1px solid hsl(var(--border-color));border-radius:10px;margin-top:14px;padding:10px 12px}.review-footer-count{color:hsl(var(--text-color));font-weight:700}.review-sidebar{position:sticky;top:20px}.review-question-nav{padding:15px}.review-question-nav h2{font-size:1rem;margin:0 0 12px}.review-question-grid{display:grid;gap:7px;grid-template-columns:repeat(5,minmax(0,1fr))}.review-question-link{align-items:center;background:hsl(var(--body-bg));border:1px solid hsl(var(--border-color));border-radius:6px;color:hsl(var(--text-color));display:flex;height:36px;justify-content:center}.review-question-link.is-current{background:hsl(var(--base)/.1);border-color:hsl(var(--base));color:hsl(var(--base));font-weight:800}.review-question-nav p{color:hsl(var(--text-color));font-size:.8rem;margin:12px 0 0}.review-main{min-width:0;position:relative}.review-layout.is-loading .review-main{opacity:.62;pointer-events:none}.review-layout.is-loading .review-main::after{background:hsl(var(--section-bg)/.96);border:1px solid hsl(var(--base)/.45);border-radius:999px;color:hsl(var(--base));content:"Loading question…";font-size:.85rem;font-weight:700;left:50%;padding:8px 14px;position:absolute;top:24px;transform:translateX(-50%);z-index:3}
        @media(max-width:900px){.review-layout{grid-template-columns:minmax(0,1fr)}.review-sidebar{position:static;grid-row:1}.review-question-grid{grid-template-columns:repeat(auto-fill,minmax(36px,1fr))}}@media(max-width:600px){.exam-review-page{padding-top:12px}.review-header-top{align-items:flex-start;flex-direction:column}.review-title-row{align-items:flex-start;flex-direction:column}.review-question-card{padding:14px}.review-match-row{grid-template-columns:1fr}.review-footer{gap:8px}.review-footer .btn{padding-left:10px;padding-right:10px}.review-option{align-items:flex-start}.review-option-note{font-size:.7rem}}
    </style>
    <style>
        .exam-return-link{align-items:center;display:inline-flex;gap:10px;justify-content:center;min-height:44px;padding:10px 16px;background:hsl(var(--section-bg));border:1px solid hsl(var(--border-color));border-radius:8px;color:hsl(var(--white));line-height:1.2;text-decoration:none;white-space:nowrap;transition:background-color .18s ease,border-color .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}.exam-return-link i{display:block;flex:0 0 auto;font-size:1rem;line-height:1;margin:0}.exam-return-link span{display:block;line-height:1.2}.exam-return-link:hover,.exam-return-link:focus-visible{background:hsl(var(--base)/.1);border-color:hsl(var(--base));color:hsl(var(--base));box-shadow:0 5px 14px hsl(var(--black)/.2);transform:translateY(-1px)}.exam-return-link:focus-visible{outline:2px solid hsl(var(--base));outline-offset:2px}.exam-return-link:active{transform:translateY(0)}.review-mode-badge{align-items:center;display:inline-flex;gap:7px;line-height:1.15}.review-mode-badge i{display:block;flex:0 0 auto;line-height:1}
        @media(prefers-reduced-motion:reduce){.exam-return-link{transition:none}}
    </style>
@endpush

@push('script')
    <script>
        (() => {
            let reviewRequestPending = false;

            async function loadReviewQuestion(url, updateHistory = true) {
                if (reviewRequestPending) return;
                reviewRequestPending = true;

                const layout = document.querySelector('.review-layout');
                const scrollPosition = window.scrollY;
                layout?.classList.add('is-loading');
                layout?.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-cache' }
                    });
                    if (!response.ok) throw new Error('Could not load the selected question.');

                    const documentFromResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const nextTitle = documentFromResponse.querySelector('.review-title-row');
                    const nextMain = documentFromResponse.querySelector('.review-main');
                    const nextNavigation = documentFromResponse.querySelector('.review-question-nav');
                    const currentTitle = document.querySelector('.review-title-row');
                    const currentMain = document.querySelector('.review-main');
                    const currentNavigation = document.querySelector('.review-question-nav');

                    if (!nextTitle || !nextMain || !nextNavigation || !currentTitle || !currentMain || !currentNavigation) {
                        throw new Error('Review page response was incomplete.');
                    }

                    currentTitle.replaceWith(nextTitle);
                    currentMain.replaceWith(nextMain);
                    currentNavigation.replaceWith(nextNavigation);
                    if (updateHistory) history.pushState({}, '', url);

                    const currentLayout = document.querySelector('.review-layout');
                    currentLayout?.classList.remove('is-loading');
                    currentLayout?.removeAttribute('aria-busy');
                    const previousScrollBehavior = document.documentElement.style.scrollBehavior;
                    document.documentElement.style.scrollBehavior = 'auto';
                    window.scrollTo(0, scrollPosition);
                    document.documentElement.style.scrollBehavior = previousScrollBehavior;
                } catch (error) {
                    window.location.assign(url);
                } finally {
                    reviewRequestPending = false;
                }
            }

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[data-review-navigation]');
                if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                loadReviewQuestion(link.href);
            });

            window.addEventListener('popstate', () => loadReviewQuestion(window.location.href, false));
        })();
    </script>
@endpush
