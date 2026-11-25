@extends('admin.layouts.app')

@section('panel')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.exams.store', $exam?->id ?? 0) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="field-wrapper mb-4">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exam Image')</p>
                                <p class="note">@lang('Choose the image shown for this exam.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <x-image-uploader image="{{ $exam?->image ?? '' }}" class="w-100" type="exam" :required="!$exam" />
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Category')</p>
                                <p class="note">@lang('Select the category this exam belongs to.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <select name="category_id" class="form-control select2" required>
                                    <option value="" disabled @selected(!old('category_id', $exam?->category_id))>@lang('Select One')</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id', $exam?->category_id) == $category->id)>{{ __($category->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exam Name')</p>
                                <p class="note">@lang('Enter the name shown to users, for example Exam A, Exam B, or Exam C.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input type="text" name="title" class="form-control" value="{{ old('title', $exam?->title) }}" maxlength="255" required>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Display Order')</p>
                                <p class="note">@lang('Lower order numbers appear first in the user exam list.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $exam?->sort_order ?? $nextSortOrder) }}" min="1" step="1" required>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Pass Mark')</p>
                                <p class="note">@lang('Minimum percentage required to pass this exam.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="pass_percentage" class="form-control" value="{{ old('pass_percentage', $exam?->pass_percentage ?? 70) }}" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn--primary w-100 h-45 mt-3" type="submit">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.exams.index') }}" />
@endpush

@push('style')
    <style>
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

