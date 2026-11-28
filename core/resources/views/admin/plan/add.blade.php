@extends('admin.layouts.app')
@section('panel')
    @php
        $selectedCategories = array_map('intval', (array) old('allowed_categories', $plan?->allowed_categories ?? []));
        $selectedExams = array_map('intval', (array) old('allowed_exams', $plan?->allowed_exams ?? []));
        $selectedQuestionLimits = (array) old('exam_question_limits', $plan?->exam_question_limits ?? []);
    @endphp
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-card-style">
                <div class="card-body">
                    <form action="{{ route('admin.plan.store', $plan?->id ?? 0) }}" method="POST" data-plan-billing-form data-annual-discount="{{ (float) config('billing.annual_discount_percent', 20) }}">
                        @csrf

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Name')</p>
                                <p class="note">@lang('The name of the pricing plan (e.g., Basic, Pro, Premium).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="text" name="name" value="{{ $plan?->name ?? '' }}" required>
                            </div>
                        </div>

                        <div class="field-wrapper align-items-start">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exam Categories')</p>
                                <p class="note">@lang('Choose the categories included in this plan.')</p>
                            </div>
                            <div class="field-wrapper-view plan-access-grid">
                                @foreach ($categories as $category)
                                    <label class="plan-access-option">
                                        <input type="checkbox" name="allowed_categories[]" value="{{ $category->id }}" @checked(in_array((int) $category->id, $selectedCategories, true))>
                                        <span>{{ __($category->name) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="field-wrapper align-items-start">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exam Banks')</p>
                                <p class="note">@lang('Choose the banks users can open under this plan.')</p>
                            </div>
                            <div class="field-wrapper-view plan-access-grid">
                                @foreach ($categories as $category)
                                    <div class="plan-access-group">
                                        <p class="fw-semibold mb-2">{{ __($category->name) }}</p>
                                        @foreach ($category->exams as $exam)
                                            @php
                                                $availableQuestions = (int) ($exam->available_questions ?? 0);
                                                $questionLimit = (int) data_get($selectedQuestionLimits, $exam->id, $availableQuestions);
                                                $examSelected = in_array((int) $exam->id, $selectedExams, true);
                                            @endphp
                                            <div class="plan-exam-access-row">
                                                <label class="plan-access-option mb-0">
                                                    <input class="plan-exam-toggle" type="checkbox" name="allowed_exams[]" value="{{ $exam->id }}" data-category-id="{{ $category->id }}" data-question-limit-toggle="{{ $exam->id }}" @checked($examSelected)>
                                                    <span>{{ __($exam->title) }}</span>
                                                </label>
                                                <label class="plan-question-limit">
                                                    <span>@lang('Questions')</span>
                                                    <input type="number" name="exam_question_limits[{{ $exam->id }}]" value="{{ min($availableQuestions, max(1, $questionLimit)) }}" min="1" max="{{ $availableQuestions }}" data-question-limit-input="{{ $exam->id }}" @disabled(!$examSelected || $availableQuestions < 1)>
                                                    <span>/ {{ $availableQuestions }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Simulation Mode')</p>
                                <p class="note">@lang('Allow users to start timed random simulation attempts.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="form--switch base justify-content-start">
                                    <input class="form-check-input" type="checkbox" name="simulation_mode" value="1" @checked(old('simulation_mode', $plan?->simulation_mode ?? false))>
                                    <span class="form-check-label">@lang('Available in this plan')</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper align-items-start">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Plan Features')</p>
                                <p class="note">@lang('Choose which extra features are included.')</p>
                            </div>
                            <div class="field-wrapper-view plan-access-grid">
                                <label class="plan-access-option">
                                    <input type="checkbox" name="feature_answer_explanations" value="1" @checked(old('feature_answer_explanations', $plan?->hasFeature('answer_explanations') ?? false))>
                                    <span>@lang('Answer explanations')</span>
                                </label>
                                <label class="plan-access-option">
                                    <input type="checkbox" name="feature_progress_tracking" value="1" @checked(old('feature_progress_tracking', $plan?->hasFeature('progress_tracking') ?? false))>
                                    <span>@lang('Progress tracking')</span>
                                </label>
                                <label class="plan-access-option">
                                    <input type="checkbox" name="feature_certificate" value="1" @checked(old('feature_certificate', $plan?->hasFeature('certificate') ?? false))>
                                    <span>@lang('Completion certificate')</span>
                                </label>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Free Plan')</p>
                                <p class="note">@lang('Assign this plan automatically to new registrations.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="form--switch base justify-content-start">
                                    <input class="form-check-input" type="checkbox" name="is_free" value="1" @checked(old('is_free', $plan?->is_free ?? false))>
                                    <span class="form-check-label">@lang('Automatic free plan')</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Monthly Price')</p>
                                <p class="note">@lang('The subscription cost charged on a monthly basis.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input class="form-control" id="monthlyPrice" type="number" step="any" min="0" name="monthly_price" value="{{ getAmount($plan?->monthly_price) ?? '' }}" required>
                                    <span class="input-group-text">{{ gs('cur_text') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Yearly Price')</p>
                                <p class="note">@lang('Calculated automatically from the monthly price with an annual discount of') {{ (float) config('billing.annual_discount_percent', 20) }}%.</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input class="form-control" id="yearlyPrice" type="number" step="any" min="0" name="yearly_price" value="{{ getAmount($plan?->yearly_price) ?? '' }}" readonly required>
                                    <span class="input-group-text">{{ gs('cur_text') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Monthly Exam Limit')</p>
                                <p class="note">@lang('The number of monthly exam limit included in the plan for the examination')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" id="monthlyExamLimit" type="number" min="0" name="monthly_exam" value="{{ $plan?->monthly_exam ?? '' }}" required>
                            </div>
                        </div>
                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Yearly Exam Limit')</p>
                                <p class="note">@lang('The number of yearly exam limit included in the plan for the examination.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" id="yearlyExamLimit" type="number" min="0" name="yearly_exam" value="{{ $plan?->yearly_exam ?? '' }}" readonly required>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Short Description')</p>
                                <p class="note">@lang('The short description about this plan.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="text" name="short_description" value="{{ $plan?->short_description ?? '' }}" required>
                            </div>
                        </div>

                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.plan.index') }}" />
@endpush


@push('style')
    <style>
        .iconpicker .iconpicker-item {
            color: rgba(0, 0, 0, 0.8);
        }

        .card.bg-card-style .card-heading {
            padding: 12px 16px;
            background-color: rgb(0, 0, 0, 5%);
            border-radius: 8px;
            margin-bottom: 24px
        }

        .card.bg-card-style .card-body {}

        .field-wrapper {
            display: flex;
            align-items: center;
            gap: 16px 24px;
            flex-wrap: wrap;
        }

        .field-wrapper:not(:last-child) {
            margin-bottom: 24px;
        }

        .field-wrapper-label {
            width: 320px;
        }

        .field-wrapper-label .title {
            color: black;
            font-weight: 600;
        }

        .field-wrapper-label .note {
            font-size: 0.75rem;
        }

        .field-wrapper-view {
            flex: 1;
            max-width: 700px;
        }

        .plan-access-grid {
            display: grid;
            gap: 10px;
        }

        .plan-access-group {
            padding: 14px;
            border: 1px solid hsl(var(--border-color));
            border-radius: 8px;
        }

        .plan-access-option {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .plan-access-option:last-child {
            margin-bottom: 0;
        }

        .plan-exam-access-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid hsl(var(--border-color) / .45);
        }

        .plan-exam-access-row:last-child {
            border-bottom: 0;
        }

        .plan-question-limit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            font-size: .8rem;
        }

        .plan-question-limit input {
            width: 76px;
            padding: 5px 8px;
        }

        .field-wrapper .image-upload-wrapper {
            height: 150px;
            position: relative;
            width: 160px;
        }

        @media (max-width: 767px) {
            .field-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }

            .field-wrapper-label {
                width: 100%;
            }
        }
    </style>
@endpush

@push('script')
    <script>
        (function() {
            var billingForm = document.querySelector('[data-plan-billing-form]');
            var monthlyPrice = document.getElementById('monthlyPrice');
            var yearlyPrice = document.getElementById('yearlyPrice');
            var monthlyExamLimit = document.getElementById('monthlyExamLimit');
            var yearlyExamLimit = document.getElementById('yearlyExamLimit');

            function syncDerivedBillingFields() {
                if (!billingForm) return;

                var monthly = Math.max(0, parseFloat(monthlyPrice?.value || 0) || 0);
                var discount = Math.max(0, Math.min(100, parseFloat(billingForm.dataset.annualDiscount || 20) || 20));
                var annual = monthly ? monthly * 12 * (1 - discount / 100) : 0;
                var monthlyAttempts = Math.max(0, parseInt(monthlyExamLimit?.value || 0, 10) || 0);

                if (yearlyPrice) yearlyPrice.value = annual.toFixed(2);
                if (yearlyExamLimit) yearlyExamLimit.value = String(monthlyAttempts * 12);
            }

            monthlyPrice?.addEventListener('input', syncDerivedBillingFields);
            monthlyExamLimit?.addEventListener('input', syncDerivedBillingFields);
            syncDerivedBillingFields();

            document.querySelectorAll('[data-question-limit-toggle]').forEach(function(toggle) {
                var input = document.querySelector('[data-question-limit-input="' + toggle.value + '"]');
                if (!input) return;
                var sync = function() {
                    input.disabled = !toggle.checked;
                };
                toggle.addEventListener('change', sync);
                sync();
            });
        })();
    </script>
@endpush
