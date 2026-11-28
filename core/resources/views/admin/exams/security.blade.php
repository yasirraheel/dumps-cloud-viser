@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">@lang('Exam security controls')</h5>
                </div>
                <form action="{{ route('admin.exams.security.settings') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="list-group">
                            <div class="list-group-item d-flex flex-wrap flex-sm-nowrap gap-3 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">@lang('Security monitoring and enforcement')</h6>
                                    <p class="mb-0 text-muted small">@lang('Record blocked copy, print, inspection, fullscreen, and screen-capture related attempts during active exams.')</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <input type="checkbox" data-width="100%" data-size="large" data-onstyle="-success" data-offstyle="-danger" data-bs-toggle="toggle" data-height="35" data-on="@lang('Enabled')" data-off="@lang('Disabled')" name="exam_security_enabled" @checked((bool) $settings->exam_security_enabled)>
                                </div>
                            </div>
                            <div class="list-group-item">
                                <label class="fw-bold mb-1" for="exam_security_violation_limit">@lang('Automatic suspension threshold')</label>
                                <p class="text-muted small mb-3">@lang('The account is suspended when counted security violations reach this number. Focus changes are logged but are not counted as violations to avoid false positives.')</p>
                                <select class="form-control" id="exam_security_violation_limit" name="exam_security_violation_limit">
                                    @foreach ([2, 4, 5, 10, 20] as $limit)
                                        <option value="{{ $limit }}" @selected((int) ($settings->exam_security_violation_limit ?? 5) === $limit)>{{ $limit }} @lang('violations')</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn--primary w-100 h-45">
                            <i class="las la-save"></i> @lang('Save security settings')
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="row gy-4">
                <div class="col-sm-4 col-xl-12">
                    <div class="widget-card bg--primary">
                        <div class="widget-card__icon"><i class="las la-shield-alt"></i></div>
                        <div class="widget-card__content">
                            <h6 class="widget-card__title">@lang('Protection')</h6>
                            <h3 class="widget-card__amount">{{ $settings->exam_security_enabled ? __('Enabled') : __('Disabled') }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 col-xl-12">
                    <div class="widget-card bg--warning">
                        <div class="widget-card__icon"><i class="las la-exclamation-triangle"></i></div>
                        <div class="widget-card__content">
                            <h6 class="widget-card__title">@lang('Counted violations')</h6>
                            <h3 class="widget-card__amount">{{ number_format($totalViolations) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 col-xl-12">
                    <div class="widget-card bg--danger">
                        <div class="widget-card__icon"><i class="las la-user-slash"></i></div>
                        <div class="widget-card__content">
                            <h6 class="widget-card__title">@lang('Auto-suspended')</h6>
                            <h3 class="widget-card__amount">{{ number_format($suspendedUsers) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">@lang('Security activity')</h5>
                        <p class="text-muted small mb-0">{{ number_format($affectedUsers) }} @lang('accounts have recorded counted security events')</p>
                    </div>
                    <span class="badge badge--warning">@lang('Threshold'): {{ (int) ($settings->exam_security_violation_limit ?? 5) }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('User')</th>
                                    <th>@lang('Exam')</th>
                                    <th>@lang('Event')</th>
                                    <th>@lang('Violations')</th>
                                    <th>@lang('Details')</th>
                                    <th>@lang('IP / Browser')</th>
                                    <th>@lang('Recorded')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($events as $event)
                                    @php
                                        $isViolation = in_array($event->event, \App\Http\Controllers\Admin\ExamSecurityController::VIOLATION_EVENTS, true);
                                        $eventLabel = ucwords(str_replace('_', ' ', $event->event));
                                        $eventName = trim(($event->firstname ?? '') . ' ' . ($event->lastname ?? '')) ?: ($event->username ?? $event->email ?? 'Unknown user');
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $eventName }}</span><br>
                                            <a class="small" href="{{ route('admin.users.detail', $event->user_id) }}"><span>@</span>{{ $event->username ?? 'user' }}</a>
                                        </td>
                                        <td>{{ __($event->exam_title ?? 'Exam session') }}<br><span class="small text-muted">#{{ $event->attend_exam_id }}</span></td>
                                        <td><span class="badge {{ $isViolation ? 'badge--danger' : 'badge--dark' }}">{{ __($eventLabel) }}</span></td>
                                        <td>
                                            <span class="fw-bold">{{ number_format((int) $event->violation_count) }}</span>
                                            @if ((int) $event->user_status === \App\Constants\Status::USER_BAN)
                                                <br><span class="badge badge--danger">@lang('Suspended')</span>
                                            @endif
                                        </td>
                                        <td><code class="small">{{ \Illuminate\Support\Str::limit($event->metadata ?: '—', 70) }}</code></td>
                                        <td>{{ $event->ip_address ?: '—' }}<br><span class="small text-muted">{{ \Illuminate\Support\Str::limit($event->user_agent ?: '—', 42) }}</span></td>
                                        <td>{{ showDateTime($event->created_at) }}<br><span class="small text-muted">{{ diffForHumans($event->created_at) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="100%" class="text-center text-muted">{{ __($emptyMessage) }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($events->hasPages())
                    <div class="card-footer py-4">{{ paginateLinks($events) }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <form method="GET" class="d-flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="@lang('Search user')" style="min-width:180px">
        <select name="event" class="form-control">
            <option value="">@lang('All events')</option>
            @foreach ($eventOptions as $eventOption)
                <option value="{{ $eventOption }}" @selected(request('event') === $eventOption)>{{ ucwords(str_replace('_', ' ', $eventOption)) }}</option>
            @endforeach
        </select>
        <button class="btn btn--primary" type="submit"><i class="las la-search"></i> @lang('Filter')</button>
    </form>
@endpush
