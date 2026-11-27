@extends('Template::layouts.master')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="topbar">
                <h5 class="topbar__title mb-0">{{ __($pageTitle) }}</h5>
                <form class="search-form2">
                    <div class="input-group input--group border--gradient">
                        <input type="text" name="search" class="form-control form--control" value="{{ request()->search }}" placeholder="@lang('Search here')..." id="search" />
                        <button class="input-group-text">
                            <svg
                                 xmlns="http://www.w3.org/2000/svg"
                                 width="20"
                                 height="20"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 class="lucide lucide-search-icon lucide-search">
                                <path d="m21 21-4.34-4.34"></path>
                                <circle cx="11" cy="11" r="8"></circle>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-xl-3 col-sm-6">
                    <div class="statistics-item h-100 text-start">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <p class="statistics-item-title mb-0">@lang('Completed Attempts')</p>
                            <span class="text--base fs-3"><i class="las la-clipboard-check" aria-hidden="true"></i></span>
                        </div>
                        <h3 class="statistics-item-count mb-2">{{ $historyStats['total_attempts'] }}</h3>
                        <p class="mb-0 text-white-50">@lang('Finished exam sessions')</p>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="statistics-item h-100 text-start">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <p class="statistics-item-title mb-0">@lang('Passed Attempts')</p>
                            <span class="text--base fs-3"><i class="las la-award" aria-hidden="true"></i></span>
                        </div>
                        <h3 class="statistics-item-count mb-2">{{ $historyStats['passed_attempts'] }}</h3>
                        <p class="mb-0 text-white-50">@lang('Successful exam results')</p>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="statistics-item h-100 text-start">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <p class="statistics-item-title mb-0">@lang('Average Score')</p>
                            <span class="text--base fs-3"><i class="las la-chart-line" aria-hidden="true"></i></span>
                        </div>
                        <h3 class="statistics-item-count mb-2">{{ getAmount($historyStats['average_score']) }}%</h3>
                        <p class="mb-0 text-white-50">@lang('Across completed exams')</p>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="statistics-item h-100 text-start">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <p class="statistics-item-title mb-0">@lang('Overall Progress')</p>
                            <span class="text--base fs-3"><i class="las la-trophy" aria-hidden="true"></i></span>
                        </div>
                        <h3 class="statistics-item-count mb-2">{{ getAmount($historyStats['overall_progress']) }}%</h3>
                        <p class="mb-0 text-white-50">@lang('Pass rate so far')</p>
                    </div>
                </div>
            </div>

            <div class="table--responsive">
                <table class="table table-responsive--lg exam-history-table">
                    <thead>
                        <tr>
                            <th>@lang('Exam Title')</th>
                            <th>@lang('Start / End Time')</th>
                            <th>@lang('Pass Percentage')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($examLogs as $examLog)
                            <tr>
                                <td>
                                    <div>
                                        <span class="fw-bold text--base">
                                            {{ __($examLog?->exam?->title ?? '') }}
                                        </span>
                                    </div>
                                </td>

                                <td class="exam-history-time">
                                    @php
                                        $historyEndTime = $examLog->history_end_time
                                            ? \Illuminate\Support\Carbon::parse($examLog->history_end_time)
                                            : null;
                                    @endphp
                                    <div class="exam-history-time-line">
                                        <span class="exam-history-time-label">@lang('Start')</span>
                                        <span>{{ showDateTime($examLog->history_start_time) }}</span>
                                    </div>
                                    <div class="exam-history-time-line exam-history-time-line--end">
                                        <span class="exam-history-time-label">@lang('End')</span>
                                        @if ($historyEndTime)
                                            <time datetime="{{ $historyEndTime->toIso8601String() }}" title="{{ showDateTime($historyEndTime) }}">{{ diffForHumans($historyEndTime) }}</time>
                                        @else
                                            <span>@lang('In progress')</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <span>
                                        {{ getAmount((int) $examLog->status === Status::EXAM_COMPLETED ? $examLog->score_percentage : $examLog->pass_percentage) }}%
                                    </span>
                                </td>

                                <td>
                                    @if ((int) $examLog->status === Status::EXAM_COMPLETED)
                                        <span class="badge {{ $examLog->passed ? 'badge--success' : 'badge--danger' }}">
                                            @lang('Completed') · {{ __($examLog->passed ? 'Passed' : 'Failed') }}
                                        </span>
                                    @else
                                        @php echo $examLog->statusBadge @endphp
                                    @endif
                                </td>

                                <td class="exam-history-actions-cell">
                                    <div class="exam-history-actions">
                                    @if ($examLog->is_submit == Status::NO)
                                        @if ($examLog->exam)
                                            <a href="{{ route('user.exam.resume', $examLog->id) }}" class="btn btn--success btn--xsm" data-bs-toggle="tooltip" data-bs-title="@lang('Resume')">
                                                <i class="las la-lg la-play"></i>
                                            </a>
                                        @endif
                                    @endif
                                    <button type="button" class="btn btn--danger btn--xsm confirmationBtn" data-bs-toggle="tooltip" data-bs-title="@lang('Delete')" data-question="@lang('Delete this exam attempt and its result? This cannot be undone.')" data-action="{{ route('user.exam.delete', $examLog->id) }}">
                                        <i class="las la-lg la-trash"></i>
                                    </button>
                                    <a href="{{ route('user.exam.view', $examLog->id) }}" class="btn btn--base btn--xsm" data-bs-toggle="tooltip" data-bs-title="@lang('Details')">
                                        <i class="las la-lg la-desktop"></i>
                                    </a>
                                    @if ($examLog->status == Status::EXAM_COMPLETED)
                                        <a href="{{ route('user.exam.certificate.download', $examLog->id) }}" class="btn btn--info btn--xsm" data-bs-toggle="tooltip" data-bs-title="@lang('Certificate')">
                                            <i class="las la-lg la-certificate"></i>
                                        </a>
                                        @if ($examLog->exam)
                                            <div class="retake-action d-inline-block">
                                                <button type="button" class="btn btn--base btn--xsm retake-menu-trigger" aria-expanded="false" aria-controls="retake-menu-{{ $examLog->id }}">
                                                    <span>@lang('Retake')</span><i class="las la-angle-down" aria-hidden="true"></i>
                                                </button>
                                                <div class="dropdown-menu retake-menu" id="retake-menu-{{ $examLog->id }}" hidden>
                                                    <div>
                                                        <form method="POST" action="{{ route('user.exam.retake', ['id' => $examLog->id, 'mode' => 'all']) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="las la-list-ul" aria-hidden="true"></i><span>@lang('Retake all questions') ({{ $examLog->retake_question_counts['all'] ?? 0 }})</span></button>
                                                        </form>
                                                    </div>
                                                    <div>
                                                        <form method="POST" action="{{ route('user.exam.retake', ['id' => $examLog->id, 'mode' => 'missed']) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="las la-question-circle" aria-hidden="true"></i><span>@lang('Retake missed') ({{ $examLog->retake_question_counts['missed'] ?? 0 }})</span></button>
                                                        </form>
                                                    </div>
                                                    <div>
                                                        <form method="POST" action="{{ route('user.exam.retake', ['id' => $examLog->id, 'mode' => 'incorrect']) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="las la-times-circle" aria-hidden="true"></i><span>@lang('Retake incorrect') ({{ $examLog->retake_question_counts['incorrect'] ?? 0 }})</span></button>
                                                        </form>
                                                    </div>
                                                    <div>
                                                        <form method="POST" action="{{ route('user.exam.retake', ['id' => $examLog->id, 'mode' => 'marked']) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="las la-flag" aria-hidden="true"></i><span>@lang('Retake marked') ({{ $examLog->retake_question_counts['marked'] ?? 0 }})</span></button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100%" class="text-center">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($examLogs->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($examLogs) }}
                </div>
            @endif
        </div>
    </div>
    <x-confirmation-modal className="custom--modal" btnClose="btn-close" submitBtn="btn btn--danger btn--sm" closeBtn="btn btn--dark btn--sm" />
@endsection

@push('script')
    <script src="{{ asset('assets/templates/basic/js/exam-dropdown.js') }}" defer></script>
@endpush
