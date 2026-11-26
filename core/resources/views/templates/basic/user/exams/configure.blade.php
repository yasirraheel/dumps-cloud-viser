@extends('Template::layouts.frontend')

@section('content')
    @php
        $examSettings = array_merge([
            'mode' => 'study',
            'question_order' => 'original',
            'randomize_options' => false,
            'show_score_live' => true,
            'include_show_answer' => true,
            'inline_feedback' => true,
            'timer' => 'not_timed',
        ], $examSettings ?? []);
        $timerLabels = [
            'not_timed' => __('No timer'),
            'ccna_120' => __('120 minutes'),
            '90_mins' => __('90 minutes'),
            '60_mins' => __('60 minutes'),
            '30s_per_q' => __('30 seconds per question'),
            '60s_per_q' => __('60 seconds per question'),
            '90s_per_q' => __('90 seconds per question'),
        ];
        $selectedTimerLabel = $timerLabels[$examSettings['timer']] ?? $timerLabels['not_timed'];
    @endphp
    <section class="exam-section my-60">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-10 col-xl-8">
                    <div class="custom--card">
                        <div class="card-header">
                            <span class="text--base fw-semibold">{{ __($exam->category?->name ?? 'Exam') }}</span>
                            <h4 class="card-title mt-1">{{ __($exam->title) }}</h4>
                            <p class="text--white mb-0 mt-1">{{ $questionCount }} @lang('active questions') · {{ getAmount($exam->pass_percentage) }}% @lang('pass mark')</p>
                        </div>

                        <form action="{{ route('user.exam.begin', $exam->slug) }}" method="POST" data-exam-settings>
                            @csrf
                            <div class="card-body">
                                <div class="mb-4">
                                    <label class="form--label">@lang('Exam mode')</label>
                                    <input type="hidden" name="mode" id="examModeValue" value="{{ $examSettings['mode'] }}">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-6">
                                            <button type="button" class="btn form--control sm-style w-100 text-start exam-mode-option {{ $examSettings['mode'] === 'study' ? 'text--base' : '' }}" data-exam-mode="study">
                                                <i class="las la-book-open me-1" aria-hidden="true"></i><span>@lang('Study mode')</span>
                                            </button>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <button type="button" class="btn form--control sm-style w-100 text-start exam-mode-option {{ $examSettings['mode'] === 'simulation' ? 'text--base' : '' }}" data-exam-mode="simulation">
                                                <i class="las la-stopwatch me-1" aria-hidden="true"></i><span>@lang('Simulation mode')</span>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text--white" id="simulationNote" @if ($examSettings['mode'] !== 'simulation') hidden @endif>@lang('Simulation selects 70% to 80% random questions from this bank. The All Questions bank selects 70 to 80 questions from the complete pool and uses a 90 minute timer.')</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form--label" for="timerLabel">@lang('Timer')</label>
                                    <input type="hidden" name="timer" id="timerValue" value="{{ $examSettings['timer'] }}">
                                    <div class="row">
                                        <div class="col-12 col-md-6">
                                            <div class="retake-action">
                                                <button type="button" class="btn form--control sm-style retake-menu-trigger text-start justify-content-start w-100" id="timerTrigger" data-menu-align="start" aria-expanded="false" aria-controls="examTimerMenu">
                                                    <span id="timerLabel">{{ $selectedTimerLabel }}</span><i class="las la-angle-down" aria-hidden="true"></i>
                                                </button>
                                                <div class="dropdown-menu retake-menu" id="examTimerMenu" hidden>
                                                    <button type="button" class="dropdown-item {{ $examSettings['timer'] === 'not_timed' ? 'text--base' : '' }}" data-timer-value="not_timed" data-timer-label="@lang('No timer')"><i class="las la-stopwatch" aria-hidden="true"></i><span>@lang('No timer')</span></button>
                                                    <button type="button" class="dropdown-item {{ $examSettings['timer'] === 'ccna_120' ? 'text--base' : '' }}" data-timer-value="ccna_120" data-timer-label="@lang('120 minutes')"><i class="las la-stopwatch" aria-hidden="true"></i><span>@lang('120 minutes')</span></button>
                                                    <button type="button" class="dropdown-item {{ $examSettings['timer'] === '90_mins' ? 'text--base' : '' }}" data-timer-value="90_mins" data-timer-label="@lang('90 minutes')"><i class="las la-stopwatch" aria-hidden="true"></i><span>@lang('90 minutes')</span></button>
                                                    <button type="button" class="dropdown-item {{ $examSettings['timer'] === '60_mins' ? 'text--base' : '' }}" data-timer-value="60_mins" data-timer-label="@lang('60 minutes')"><i class="las la-stopwatch" aria-hidden="true"></i><span>@lang('60 minutes')</span></button>
                                                    <button type="button" class="dropdown-item {{ $examSettings['timer'] === '30s_per_q' ? 'text--base' : '' }}" data-timer-value="30s_per_q" data-timer-label="@lang('30 seconds per question')"><i class="las la-hourglass-half" aria-hidden="true"></i><span>@lang('30 seconds per question')</span></button>
                                                    <button type="button" class="dropdown-item {{ $examSettings['timer'] === '60s_per_q' ? 'text--base' : '' }}" data-timer-value="60s_per_q" data-timer-label="@lang('60 seconds per question')"><i class="las la-hourglass-half" aria-hidden="true"></i><span>@lang('60 seconds per question')</span></button>
                                                    <button type="button" class="dropdown-item {{ $examSettings['timer'] === '90s_per_q' ? 'text--base' : '' }}" data-timer-value="90s_per_q" data-timer-label="@lang('90 seconds per question')"><i class="las la-hourglass-half" aria-hidden="true"></i><span>@lang('90 seconds per question')</span></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text--white">@lang('Saved with this attempt for resume.')</small>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text--base mb-2">@lang('Question order')</h6>
                                    <input type="hidden" name="question_order" id="questionOrder" value="{{ $examSettings['question_order'] }}">
                                    <div class="form--switch base justify-content-start flex-nowrap">
                                        <input class="form-check-input" type="checkbox" id="randomizeQuestions" @checked($examSettings['question_order'] === 'random')>
                                        <label class="form-check-label" for="randomizeQuestions">@lang('Randomize question order')</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text--base mb-2">@lang('Answer and score display')</h6>
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6"><div class="form--switch base justify-content-start flex-nowrap"><input class="form-check-input" type="checkbox" name="randomize_options" value="1" id="randomizeOptions" @checked($examSettings['randomize_options'])><label class="form-check-label" for="randomizeOptions">@lang('Randomize answer options')</label></div></div>
                                        <div class="col-12 col-md-6"><div class="form--switch base justify-content-start flex-nowrap"><input class="form-check-input" type="checkbox" name="show_score_live" value="1" id="showLiveScore" @checked($examSettings['show_score_live'])><label class="form-check-label" for="showLiveScore">@lang('Show live score and percentage')</label></div></div>
                                        <div class="col-12 col-md-6"><div class="form--switch base justify-content-start flex-nowrap"><input class="form-check-input" type="checkbox" name="include_show_answer" value="1" id="includeShowAnswer" @checked($examSettings['include_show_answer'])><label class="form-check-label" for="includeShowAnswer">@lang('Include Show Answer button')</label></div></div>
                                        <div class="col-12 col-md-6"><div class="form--switch base justify-content-start flex-nowrap"><input class="form-check-input" type="checkbox" name="inline_feedback" value="1" id="inlineFeedback" @checked($examSettings['inline_feedback'])><label class="form-check-label" for="inlineFeedback">@lang('Show feedback after each question')</label></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer d-flex flex-column flex-sm-row justify-content-end gap-2 px-3 pb-3 pt-2">
                                <a href="{{ route('user.exam.history') }}" class="btn btn--dark">@lang('Cancel')</a>
                                <button type="submit" class="btn btn--base">@lang('Start exam') <i class="las la-arrow-right" aria-hidden="true"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('script')
    <script src="{{ asset('assets/templates/basic/js/exam-dropdown.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modeValue = document.getElementById('examModeValue');
            const modeButtons = document.querySelectorAll('[data-exam-mode]');
            const timerValue = document.getElementById('timerValue');
            const timerLabel = document.getElementById('timerLabel');
            const randomizeQuestions = document.getElementById('randomizeQuestions');
            const questionOrder = document.getElementById('questionOrder');
            const showLiveScore = document.getElementById('showLiveScore');
            const includeShowAnswer = document.getElementById('includeShowAnswer');
            const inlineFeedback = document.getElementById('inlineFeedback');
            const simulationNote = document.getElementById('simulationNote');
            const studyDefaults = {
                timer: timerValue?.value,
                timerLabel: timerLabel?.textContent,
                randomizeQuestions: randomizeQuestions?.checked,
                showLiveScore: showLiveScore?.checked,
                includeShowAnswer: includeShowAnswer?.checked,
                inlineFeedback: inlineFeedback?.checked,
            };

            const applyMode = (mode) => {
                const simulation = mode === 'simulation';
                modeValue.value = mode;
                modeButtons.forEach((button) => button.classList.toggle('text--base', button.dataset.examMode === mode));
                simulationNote.hidden = !simulation;

                if (simulation) {
                    timerValue.value = '90_mins';
                    timerLabel.textContent = '@lang('90 minutes')';
                    randomizeQuestions.checked = true;
                    questionOrder.value = 'random';
                    showLiveScore.checked = true;
                    includeShowAnswer.checked = false;
                    inlineFeedback.checked = false;
                    randomizeQuestions.disabled = true;
                    showLiveScore.disabled = true;
                    includeShowAnswer.disabled = true;
                    inlineFeedback.disabled = true;
                } else {
                    timerValue.value = studyDefaults.timer;
                    timerLabel.textContent = studyDefaults.timerLabel;
                    randomizeQuestions.checked = studyDefaults.randomizeQuestions;
                    questionOrder.value = randomizeQuestions.checked ? 'random' : 'original';
                    showLiveScore.checked = studyDefaults.showLiveScore;
                    includeShowAnswer.checked = studyDefaults.includeShowAnswer;
                    inlineFeedback.checked = studyDefaults.inlineFeedback;
                    randomizeQuestions.disabled = false;
                    showLiveScore.disabled = false;
                    includeShowAnswer.disabled = false;
                    inlineFeedback.disabled = false;
                }
            };

            modeButtons.forEach((button) => button.addEventListener('click', () => applyMode(button.dataset.examMode)));
            randomizeQuestions?.addEventListener('change', () => {
                if (modeValue.value !== 'simulation') questionOrder.value = randomizeQuestions.checked ? 'random' : 'original';
            });
            applyMode(modeValue.value || 'study');
        });
    </script>
@endpush
