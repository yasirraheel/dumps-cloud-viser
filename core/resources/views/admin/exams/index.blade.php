@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Image')</th>
                                    <th>@lang('Title')</th>
                                    <th>@lang('Order')</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Questions')</th>
                                    <th>@lang('Pass Mark')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($exams as $exam)
                                    <tr>
                                        <td>
                                            @if ($exam->image)
                                                <img src="{{ getImage(getFilePath('exam') . '/' . $exam->image, getFileSize('exam')) }}" alt="{{ __($exam->title) }}" width="84" height="44" class="rounded" style="object-fit: cover">
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span>{{ __($exam->title) }}</span>
                                        </td>
                                        <td>
                                            <span>{{ $exam->sort_order }}</span>
                                        </td>
                                        <td>
                                            <span>{{ __($exam?->category?->name ?? '') }}</span>
                                        </td>
                                        <td>
                                            <span>{{ $exam->active_questions_count }}</span>
                                        </td>
                                        <td>
                                            <span>{{ getAmount($exam->pass_percentage) }}%</span>
                                        </td>
                                        <td>
                                            @php
                                                echo $exam->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn--sm btn-outline--info" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="las la-ellipsis-v"></i> @lang('Actions')
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{ route('admin.exams.questions', $exam->id) }}">
                                                        <i class="la la-cogs"></i> @lang('Questions')
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('admin.exams.add', $exam->id) }}">
                                                        <i class="la la-pencil"></i> @lang('Edit')
                                                    </a>
                                                    <button class="dropdown-item text-danger confirmationBtn" type="button" data-action="{{ route('admin.exams.delete', $exam->id) }}" data-question="@lang('Delete this exam and all its questions?')">
                                                        <i class="la la-trash"></i> @lang('Delete')
                                                    </button>
                                                    @if ($exam->status == Status::ENABLE)
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.status', $exam->id) }}" data-question="@lang('Are you sure to disable this exam')?">
                                                            <i class="la la-eye-slash"></i> @lang('Disable')
                                                        </button>
                                                    @else
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.status', $exam->id) }}" data-question="@lang('Are you sure to enable this exam')?">
                                                            <i class="la la-eye"></i> @lang('Enable')
                                                        </button>
                                                    @endif
                                                    @if ($exam->result_published_at < now() && $exam->result_published == Status::NO)
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.publish.result', $exam->id) }}" data-question="@lang('Are you sure to declare the result')?">
                                                            <i class="las la-gavel"></i> @lang('Declare Result')
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($exams->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($exams) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
    @if (!request()->routeIs('admin.exams.result.declare'))
        <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.exams.add') }}"><i class="las la-plus"></i>@lang('Add New')</a>
    @endif
@endpush
