@php
    $optionLetters = range('A', 'Z');
    $type = $question->question_type ?? 'single_choice';
    $answerData = is_array($question->answer_data) ? $question->answer_data : [];
    $requiredSelectionCount = $type === 'multiple_selection'
        ? \App\Support\ExamScoreCalculator::requiredSelectionCount($question)
        : 1;
    $dragItems = $answerData['items'] ?? [];
    $dragTargets = $answerData['targets'] ?? [];
    $savedOptionIds = is_array($savedAnswer) ? array_map('intval', $savedAnswer) : (($savedAnswer !== null && $savedAnswer !== '') ? [(int) $savedAnswer] : []);
    $questionTitle = (string) __($question->title);
    $questionTitleLines = preg_split('/\\R/u', trim($questionTitle)) ?: [];
    $nonEmptyQuestionTitleLines = array_values(array_filter(array_map('trim', $questionTitleLines), static fn ($line) => $line !== ''));
    $hasQuestionTitleListBreaks = preg_match('/\\R\\s*\\R/u', $questionTitle) === 1;
    $hasQuestionTitleListMarkers = preg_match('/(?:^|\\R)\\s*(?:\\*|•|-)\\s+\\S/u', $questionTitle) === 1;
    $questionTitleIsList = count($nonEmptyQuestionTitleLines) >= 4 && ($hasQuestionTitleListBreaks || $hasQuestionTitleListMarkers);
    $questionTitleIntro = $questionTitleIsList ? array_shift($nonEmptyQuestionTitleLines) : null;
    $questionTitleOutro = $questionTitleIsList ? array_pop($nonEmptyQuestionTitleLines) : null;
    $questionTitleItems = $questionTitleIsList
        ? array_map(static fn ($line) => preg_replace('/^(?:\\*|•|-)\\s*/u', '', $line), $nonEmptyQuestionTitleLines)
        : [];
    $originalSourceImage = $question->original_source_image;
    if (!$originalSourceImage && $type === 'drag_drop' && ($exam->title ?? null) === 'Drag & Drop Special Bank') {
        $sourceOrder = (int) ($question->sort_order ?? 0);
        if ($sourceOrder >= 1 && $sourceOrder <= 21) {
            $originalSourceImage = "original_sources/d{$sourceOrder}.png";
        }
    }
    $originalSourceUrl = $originalSourceImage
        ? (\Illuminate\Support\Str::startsWith($originalSourceImage, ['http://', 'https://']) ? $originalSourceImage : asset($originalSourceImage))
        : null;
    $sourceViewerId = 'original-source-' . $question->id;
@endphp

<article class="exam-step exam-question-card is-active" data-index="{{ $questionIndex }}" data-question-id="{{ $question->id }}" data-question-type="{{ $type }}" data-required-selections="{{ $requiredSelectionCount }}" id="question-{{ $question->id }}">
    <div class="exam-question-meta"><span class="exam-question-number">{{ $questionIndex + 1 }}</span><span>@lang('Question') {{ $questionIndex + 1 }}</span><span class="question-type-badge">{{ ucwords(str_replace('_', ' ', $type)) }}</span></div>
    @if ($questionTitleIsList)
        <div class="exam-qsn-title exam-qsn-title--structured">
            <p>{{ $questionTitleIntro }}</p>
            <ul>
                @foreach ($questionTitleItems as $titleItem)
                    <li>{{ $titleItem }}</li>
                @endforeach
            </ul>
            <p>{{ $questionTitleOutro }}</p>
        </div>
    @else
        <h3 class="exam-qsn-title">{{ $questionTitle }}</h3>
    @endif

    @if ($question->exhibit_image)
        <section class="exam-image-viewer" data-exam-image-viewer data-zoom="1" aria-label="@lang('Exhibit image')">
            <div class="exam-image-toolbar">
                <strong class="exam-image-title">@lang('Exhibit')</strong>
                <div class="exam-image-controls">
                    <button type="button" class="btn btn--dark btn--sm" data-zoom-action="out" aria-label="@lang('Zoom out')" title="@lang('Zoom out')">−</button>
                    <span class="exam-image-zoom-level" data-zoom-level aria-live="polite">100%</span>
                    <button type="button" class="btn btn--dark btn--sm" data-zoom-action="in" aria-label="@lang('Zoom in')" title="@lang('Zoom in')">+</button>
                    <button type="button" class="btn btn--dark btn--sm" data-zoom-action="reset">@lang('Reset')</button>
                </div>
            </div>
            <div class="exam-exhibit exam-image-frame">
                <img src="{{ asset($question->exhibit_image) }}" alt="@lang('Question exhibit')" data-zoom-image>
            </div>
        </section>
    @endif
    @if ($originalSourceUrl)
        <div class="exam-source-toggle-row">
            <button type="button" class="btn btn--sm exam-source-toggle" data-source-toggle data-open-label="@lang('Hide Original Source')" data-closed-label="@lang('Original Source')" aria-expanded="false" aria-controls="{{ $sourceViewerId }}">
                <i class="las la-file-image" aria-hidden="true"></i>
                <span data-source-toggle-label>@lang('Original Source')</span>
            </button>
        </div>
        <section class="exam-image-viewer exam-source-viewer" id="{{ $sourceViewerId }}" data-source-panel data-exam-image-viewer data-zoom="1" aria-label="@lang('Original source image')" hidden>
            <div class="exam-image-toolbar">
                <strong class="exam-image-title">@lang('Original Source')</strong>
                <div class="exam-image-controls">
                    <button type="button" class="btn btn--dark btn--sm" data-zoom-action="out" aria-label="@lang('Zoom out')" title="@lang('Zoom out')">−</button>
                    <span class="exam-image-zoom-level" data-zoom-level aria-live="polite">100%</span>
                    <button type="button" class="btn btn--dark btn--sm" data-zoom-action="in" aria-label="@lang('Zoom in')" title="@lang('Zoom in')">+</button>
                    <button type="button" class="btn btn--dark btn--sm" data-zoom-action="reset">@lang('Reset')</button>
                </div>
            </div>
            <div class="exam-exhibit exam-image-frame">
                <img src="{{ $originalSourceUrl }}" alt="@lang('Original source for question :number', ['number' => $questionIndex + 1])" data-zoom-image>
            </div>
        </section>
    @endif
    @if ($question->cli_snippet)
        <pre class="exam-cli-snippet"><code>{{ $question->cli_snippet }}</code></pre>
    @endif

    @if ($type === 'drag_drop')
        <div class="drag-question drag-drop-container" data-drag-question="{{ $question->id }}">
            <div class="drag-drop-layout">
                <div class="drag-pool-column">
                    <div class="column-header"><span class="column-title">📋 @lang('Available items')</span><span class="column-count drag-item-count">{{ count($dragItems) }} @lang('left')</span></div>
                    <div class="items-pool drag-items">
                        @foreach ($dragItems as $item)
                            <div class="drag-item-card drag-item" draggable="true" data-item="{{ $item }}" title="@lang('Click to select or drag to a target')"><span class="drag-handle">⠿</span><span class="drag-text drag-item-text">{{ $item }}</span><span class="drag-assigned-badge"><span class="badge-used">@lang('Used')</span><span class="badge-recall">@lang('Recall') ✕</span></span></div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-ui btn-reset-dd drag-reset d-none"><span class="drag-action-icon" aria-hidden="true">↺</span><span>@lang('Reset all matches')</span></button>
                </div>
                <div class="drag-targets-column">
                    <div class="column-header"><span class="column-title">🎯 @lang('Target slots')</span><span class="column-count drag-match-count">0 / {{ count($dragTargets) }} @lang('assigned')</span></div>
                    <div class="targets-list drag-targets">
                        @foreach (array_values($dragTargets) as $targetIndex => $target)
                            @php $targetLabel = is_scalar($target) ? (string) $target : ''; @endphp
                            <div class="target-slot-card drag-target" data-target="{{ $targetLabel }}">
                                <div class="target-label"><span class="target-num">{{ $targetIndex + 1 }}.</span><span class="target-name">{{ $targetLabel }}</span></div>
                                <div class="target-drop-zone drag-target-slot"><span class="drop-placeholder drag-placeholder"><span class="empty-hint">@lang('Drop item here')</span></span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="dd-confirm-wrapper"><button type="button" class="btn btn-ui btn-confirm-dd drag-confirm"><span class="drag-action-icon" aria-hidden="true">✓</span><span>@lang('Confirm Drag & Drop Matches') (<span class="drag-confirm-count">0/{{ count($dragTargets) }}</span>)</span></button></div>
            <input type="hidden" name="answer[{{ $question->id }}]" value="1"><input type="hidden" name="answer_data[{{ $question->id }}]" class="drag-answer-data" value='@json($savedDragData)'>
        </div>
    @else
        <div class="exam-qsn-list">
            @foreach ($question->options as $option)
                @php
                    $optionTitle = preg_replace('/^\s*[A-Z](?:[\.\)\:\-])\s*/i', '', (string) $option->title);
                    $displayOptionTitle = $optionTitle;
                    $isCliOption = preg_match('/^\s*(?:[\w.-]+(?:\([^)]*\))?[>#]|enable\b|line\s+(?:con(?:sole)?|vty)\b|interface\b|router\b|ip\s+(?:access-list|route|address|default-gateway)\b|switchport\b|crypto\b|hostname\b|username\b|service\b|no\s+\S)/i', $optionTitle);
                    if ($isCliOption) {
                        $displayOptionTitle = preg_replace('/\s+(?=[\w.-]+(?:\([^)]*\))?[>#])/', "\n", trim($displayOptionTitle));
                        $displayOptionTitle = preg_replace('/\s+(?=(?:line\s+(?:con(?:sole)?|vty)\b|interface\s+\S+|router\s+\S+))/', "\n", $displayOptionTitle);
                        $displayOptionTitle = preg_replace('/\s+(?=(?:password\s+\S+|login\b|transport\s+input\b|exec-timeout\b|access-class\b|logging\s+synchronous\b|authorization\b|privilege\s+level\b))/i', "\n  ", $displayOptionTitle);
                    }
                @endphp
                <label class="exam-qsn-item {{ $type === 'multiple_selection' ? 'exam-qsn-item--multiple' : '' }}" for="qsn-{{ $question->id }}-{{ $option->id }}">@if ($type === 'multiple_selection')<span class="exam-option-selection-mark" aria-hidden="true"></span>@endif<span class="exam-qsn-item__label">{{ $optionLetters[$loop->index] ?? $loop->iteration }}</span><span class="exam-option-text">{!! nl2br(e(__($displayOptionTitle)), false) !!}</span><input type="{{ $type === 'multiple_selection' ? 'checkbox' : 'radio' }}" class="d-none answer-input" name="answer[{{ $question->id }}]{{ $type === 'multiple_selection' ? '[]' : '' }}" value="{{ $option->id }}" id="qsn-{{ $question->id }}-{{ $option->id }}" @checked(in_array($option->id, $savedOptionIds))></label>
            @endforeach
        </div>
    @endif

    @if ($revealed)
        <div class="alert d-none answer-feedback" role="status"></div>
        <div class="exam-explanation is-visible">{!! $question->explanationForDisplayedOptionOrder() ?: '<p>' . __('No explanation has been added yet.') . '</p>' !!}</div>
    @endif
</article>
