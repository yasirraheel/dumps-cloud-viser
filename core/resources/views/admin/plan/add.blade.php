@extends('admin.layouts.app')
@section('panel')
    @php
        $selectedCategories = array_map('intval', (array) old('allowed_categories', $plan?->allowed_categories ?? []));
        $selectedExams = array_map('intval', (array) old('allowed_exams', $plan?->allowed_exams ?? []));
    @endphp
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-card-style">
                <div class="card-body">
                    <form action="{{ route('admin.plan.store', $plan?->id ?? 0) }}" method="POST">
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
                                            <label class="plan-access-option">
                                                <input type="checkbox" name="allowed_exams[]" value="{{ $exam->id }}" data-category-id="{{ $category->id }}" @checked(in_array((int) $exam->id, $selectedExams, true))>
                                                <span>{{ __($exam->title) }}</span>
                                            </label>
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
                                    <input class="form-control" type="number" step="any" name="monthly_price" value="{{ getAmount($plan?->monthly_price) ?? '' }}" required>
                                    <span class="input-group-text">{{ gs('cur_text') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Yearly Price')</p>
                                <p class="note">@lang('The subscription cost charged annually (often discounted from monthly pricing).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input class="form-control" type="number" step="any" name="yearly_price" value="{{ getAmount($plan?->yearly_price) ?? '' }}" required>
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
                                <input class="form-control" type="number" name="monthly_exam" value="{{ $plan?->monthly_exam ?? '' }}" required>
                            </div>
                        </div>
                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Yearly Exam Limit')</p>
                                <p class="note">@lang('The number of yearly exam limit included in the plan for the examination.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input class="form-control" type="number" name="yearly_exam" value="{{ $plan?->yearly_exam ?? '' }}" required>
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
