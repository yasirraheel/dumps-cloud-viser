@extends('admin.layouts.app')

@php
    $questionType = old('question_type', $question?->question_type ?? 'single_choice');
    $optionValues = old('options', $options ?: array_fill(0, 4, ''));
    $optionValues = is_array($optionValues) ? array_values($optionValues) : array_fill(0, 4, '');
    $selectedMultipleAnswers = array_map('intval', (array) old('correct_options', $correctIndices));
    $selectedSingleAnswer = old('correct_option', $correctIndices[0] ?? null);
    $dragDropValue = old('drag_drop_json', $question?->question_type === 'drag_drop'
        ? json_encode($question->answer_data ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : '');
    $exhibitImageUrl = $question?->exhibit_image
        ? (\Illuminate\Support\Str::startsWith($question->exhibit_image, ['http://', 'https://']) ? $question->exhibit_image : asset($question->exhibit_image))
        : null;
    $originalSourceImageUrl = $question?->original_source_image
        ? (\Illuminate\Support\Str::startsWith($question->original_source_image, ['http://', 'https://']) ? $question->original_source_image : asset($question->original_source_image))
        : null;
@endphp

@section('panel')
    <div class="row justify-content-center">
        <div class="col-xxl-10 col-xl-11">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">{{ __($pageTitle) }}</h5>
                        <small class="text-muted">{{ __($exam->title) }}</small>
                    </div>
                    <span class="badge badge--info">{{ __($exam->category?->name ?? '') }}</span>
                </div>

                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert--danger mb-4">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.exams.question.store', [$exam->id, $question?->id]) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Question Number / Label')</p>
                                <p class="note">@lang('Optional reference shown with this question.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" name="question_no" type="text" maxlength="30"
                                    value="{{ old('question_no', $question?->question_no) }}"
                                    placeholder="@lang('e.g. Question #102')">
                                @error('question_no') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Question') <span class="text-danger">*</span></p>
                                <p class="note">@lang('Enter the complete question statement.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <textarea class="form-control" name="title" rows="5" required>{{ old('title', $question?->title) }}</textarea>
                                @error('title') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Question Type') <span class="text-danger">*</span></p>
                                <p class="note">@lang('Choose one of the supported question formats.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <select class="form-control" name="question_type" id="questionType" required>
                                    <option value="single_choice" @selected($questionType === 'single_choice')>@lang('Multiple choice (one answer)')</option>
                                    <option value="multiple_selection" @selected($questionType === 'multiple_selection')>@lang('Multiple selection')</option>
                                    <option value="drag_drop" @selected($questionType === 'drag_drop')>@lang('Drag & drop')</option>
                                </select>
                                @error('question_type') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div id="choiceFields" class="field-wrapper {{ $questionType === 'drag_drop' ? 'd-none' : '' }}">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Answer Options')</p>
                                <p class="note" id="choiceHint">@lang('Add each option, then select the correct answer.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="d-flex justify-content-end mb-3">
                                    <button class="btn btn-sm btn-outline--primary" type="button" id="addChoiceOption">
                                        <i class="las la-plus"></i> @lang('Add option')
                                    </button>
                                </div>
                                <div id="choiceOptions">
                                    @foreach ($optionValues as $index => $optionValue)
                                        @php
                                            $isCorrect = $questionType === 'multiple_selection'
                                                ? in_array($index, $selectedMultipleAnswers, true)
                                                : (string) $selectedSingleAnswer === (string) $index;
                                        @endphp
                                        <div class="input-group mb-2 question-option-row">
                                            <span class="input-group-text">
                                                @if ($questionType === 'multiple_selection')
                                                    <input type="checkbox" name="correct_options[]" value="{{ $index }}" class="correct-option-check" title="@lang('Correct answer')" @checked($isCorrect)>
                                                @else
                                                    <input type="radio" name="correct_option" value="{{ $index }}" class="correct-option-radio" title="@lang('Correct answer')" @checked($isCorrect)>
                                                @endif
                                            </span>
                                            <input class="form-control" name="options[]" value="{{ $optionValue }}" placeholder="@lang('Option text')" required>
                                            <button class="btn btn-outline--danger remove-option" type="button" aria-label="@lang('Remove option')">
                                                <i class="las la-times"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                                @error('options') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                @error('correct_option') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                @error('correct_options') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div id="dragFields" class="field-wrapper {{ $questionType === 'drag_drop' ? '' : 'd-none' }}">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Drag & Drop Data') <span class="text-danger">*</span></p>
                                <p class="note">@lang('Define the draggable items, target slots, and correct matches.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <textarea class="form-control font-monospace" name="drag_drop_json" id="dragDropJson" rows="12"
                                    placeholder='{"items":["Item 1","Item 2"],"targets":["Target 1","Target 2"],"correctMatches":{"Target 1":"Item 1","Target 2":"Item 2"}}'>{{ $dragDropValue }}</textarea>
                                <small class="text-muted">@lang('JSON keys: items, targets, correctMatches')</small>
                                @error('drag_drop_json') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Explanation')</p>
                                <p class="note">@lang('Explanation shown after answering, according to exam settings.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <textarea class="form-control nicEdit" name="explanation" rows="10" placeholder="@lang('Explain why the answer is correct.')">{{ old('explanation', $question?->explanation) }}</textarea>
                                @error('explanation') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exhibit Image')</p>
                                <p class="note">@lang('Choose an exhibit diagram image.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="file" name="exhibit_image_upload" id="exhibitImagePicker" accept=".jpg,.jpeg,.png,.webp">
                                <img id="exhibitImagePreview" class="img-thumbnail mt-3 {{ $exhibitImageUrl ? '' : 'd-none' }}"
                                    src="{{ $exhibitImageUrl ?? '' }}" alt="@lang('Exhibit preview')" style="max-height: 420px; max-width: 100%; object-fit: contain;">
                                <small id="exhibitImageName" class="text-muted d-block mt-2">{{ $question?->exhibit_image ? basename($question->exhibit_image) : '' }}</small>
                                @error('exhibit_image_upload') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Original Source Image')</p>
                                <p class="note">@lang('Choose the original source image.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="file" name="original_source_image_upload" id="originalSourceImagePicker" accept=".jpg,.jpeg,.png,.webp">
                                <img id="originalSourceImagePreview" class="img-thumbnail mt-3 {{ $originalSourceImageUrl ? '' : 'd-none' }}"
                                    src="{{ $originalSourceImageUrl ?? '' }}" alt="@lang('Original source preview')" style="max-height: 420px; max-width: 100%; object-fit: contain;">
                                <small id="originalSourceImageName" class="text-muted d-block mt-2">{{ $question?->original_source_image ? basename($question->original_source_image) : '' }}</small>
                                @error('original_source_image_upload') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('CLI Snippet')</p>
                                <p class="note">@lang('Optional command output or configuration snippet.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <textarea class="form-control font-monospace" name="cli_snippet" rows="5" placeholder="Router# show running-config">{{ old('cli_snippet', $question?->cli_snippet) }}</textarea>
                                @error('cli_snippet') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end flex-wrap gap-2 mt-4">
                            <a class="btn btn--dark" href="{{ route('admin.exams.questions', $exam->id) }}">@lang('Cancel')</a>
                            <button class="btn btn--primary" type="submit">
                                <i class="las la-save"></i> {{ $question ? __('Save Changes') : __('Add Question') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.exams.questions', $exam->id) }}" />
@endpush

@push('script')
    <script>
        (function ($) {
            'use strict';

            const escapeHtml = (value) => $('<div>').text(value ?? '').html();

            function optionRow(value = '', index = 0, type = 'single_choice', checked = false) {
                const answerControl = type === 'multiple_selection'
                    ? `<input type="checkbox" name="correct_options[]" value="${index}" class="correct-option-check" title="@lang('Correct answer')" ${checked ? 'checked' : ''}>`
                    : `<input type="radio" name="correct_option" value="${index}" class="correct-option-radio" title="@lang('Correct answer')" ${checked ? 'checked' : ''}>`;

                return `<div class="input-group mb-2 question-option-row">
                    <span class="input-group-text">${answerControl}</span>
                    <input class="form-control" name="options[]" value="${escapeHtml(value)}" placeholder="@lang('Option text')" required>
                    <button class="btn btn-outline--danger remove-option" type="button" aria-label="@lang('Remove option')"><i class="las la-times"></i></button>
                </div>`;
            }

            function updateOptionControls() {
                const type = $('#questionType').val();
                const isMultiple = type === 'multiple_selection';
                $('#choiceOptions .question-option-row').each(function (index) {
                    $(this).find('.correct-option-check, .correct-option-radio').val(index);
                });
                $('#choiceOptions .correct-option-radio').prop('required', false).first().prop('required', type === 'single_choice');
                $('#choiceHint').text(isMultiple
                    ? @json(__('Select every correct answer.'))
                    : @json(__('Select the single correct answer.')));
            }

            $('#questionType').on('change', function () {
                const type = $(this).val();
                const isDragDrop = type === 'drag_drop';
                const optionValues = $('#choiceOptions [name="options[]"]').map(function () { return $(this).val(); }).get();
                const selectedAnswers = $('#choiceOptions .correct-option-check:checked, #choiceOptions .correct-option-radio:checked')
                    .map(function () { return Number($(this).val()); }).get();

                $('#choiceFields').toggleClass('d-none', isDragDrop);
                $('#dragFields').toggleClass('d-none', !isDragDrop);
                $('#choiceFields :input').prop('disabled', isDragDrop);
                $('#dragDropJson').prop('disabled', !isDragDrop).prop('required', isDragDrop);

                if (!isDragDrop) {
                    const values = optionValues.length ? optionValues : ['', '', '', ''];
                    $('#choiceOptions').html(values.map((value, index) => optionRow(value, index, type, selectedAnswers.includes(index))).join(''));
                    updateOptionControls();
                }
            });

            $('#addChoiceOption').on('click', function () {
                const type = $('#questionType').val();
                const index = $('#choiceOptions .question-option-row').length;
                $('#choiceOptions').append(optionRow('', index, type));
                updateOptionControls();
            });

            $(document).on('click', '.remove-option', function () {
                $(this).closest('.question-option-row').remove();
                updateOptionControls();
            });

            function bindImagePicker(inputId, previewId, nameId) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const filename = document.getElementById(nameId);
                let previewUrl = null;

                input?.addEventListener('change', function () {
                    const file = this.files?.[0];
                    if (!file) return;

                    if (previewUrl) URL.revokeObjectURL(previewUrl);
                    previewUrl = URL.createObjectURL(file);
                    preview.src = previewUrl;
                    preview.classList.remove('d-none');
                    filename.textContent = file.name;
                });
            }

            bindImagePicker('exhibitImagePicker', 'exhibitImagePreview', 'exhibitImageName');
            bindImagePicker('originalSourceImagePicker', 'originalSourceImagePreview', 'originalSourceImageName');

            $('#questionType').trigger('change');
        })(jQuery);
    </script>
@endpush
