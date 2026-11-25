@extends('Template::layouts.master')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="topbar">
                <h5 class="topbar__title">{{ __($pageTitle) }}</h5>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="show-filter mb-3 text-end p-2">
                        <button type="button" class="btn btn--base showFilterBtn btn--sm"><i class="las la-filter"></i>
                            @lang('Filter')</button>
                    </div>
                    <div class="card custom--card responsive-filter-card mb-4">
                        <div class="card-body">
                            <form>
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="flex-grow-1">
                                        <label class="form-label">@lang('Transaction Number')</label>
                                        <input type="search" name="search" value="{{ request()->search }}" class="form--control">
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label d-block">@lang('Type')</label>
                                        <select name="trx_type" class="form--control select2" data-minimum-results-for-search="-1">
                                            <option value="">@lang('All')</option>
                                            <option value="+" @selected(request()->trx_type == '+')>@lang('Plus')</option>
                                            <option value="-" @selected(request()->trx_type == '-')>@lang('Minus')</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label d-block">@lang('Remark')</label>
                                        <select class="form--control select2" data-minimum-results-for-search="-1" name="remark">
                                            <option value="">@lang('All')</option>
                                            @foreach ($remarks as $remark)
                                                <option value="{{ $remark->remark }}" @selected(request()->remark == $remark->remark)>
                                                    {{ __(keyToTitle($remark->remark)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 align-self-end">
                                        <button class="btn btn--base w-100"><i class="las la-filter"></i>
                                            @lang('Filter')</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="table--responsive">
                        <table class="table table-responsive--lg">
                            <thead>
                                <tr>
                                    <th>@lang('Trx')</th>
                                    <th>@lang('Transacted')</th>
                                    <th>@lang('Amount')</th>
                                    <th>@lang('Post Balance')</th>
                                    <th>@lang('Detail')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $trx)
                                    <tr>
                                        <td>
                                            <div>
                                                <p class="fw-semibold fs-14">
                                                    {{ $trx->trx }}
                                                </p>
                                            </div>
                                        </td>

                                        <td>
                                            <div>
                                                <p class="fw-semibold fs-14">
                                                    {{ showDateTime($trx->created_at, 'd M Y h:i A') }}
                                                </p>
                                                <p class="fs-14">
                                                    {{ diffForHumans($trx->created_at) }}
                                                </p>
                                            </div>
                                        </td>

                                        <td>
                                            <div>
                                                <p
                                                   class="fw-semibold fs-14 @if ($trx->trx_type == '+') text--success @else text--danger @endif">
                                                    {{ $trx->trx_type }} {{ showAmount($trx->amount) }}
                                                </p>
                                            </div>
                                        </td>

                                        <td>
                                            <div>
                                                <p class="fw-semibold fs-14">
                                                    {{ showAmount($trx->post_balance) }}
                                                </p>
                                            </div>
                                        </td>


                                        <td>
                                            <div>
                                                <p class="fw-semibold fs-14">
                                                    {{ __($trx->details) }}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100%" class="text-center">
                                            {{ __($emptyMessage) }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($transactions->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($transactions) }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('style-lib')
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
@endpush
