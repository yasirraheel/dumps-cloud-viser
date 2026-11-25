@extends('Template::layouts.master')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="topbar">
                <h5 class="topbar__title">{{ __($pageTitle) }}</h5>
                <form class="search-form2">
                    <div class="input-group input--group border--gradient">
                        <input type="text" name="search" class="form-control form--control" value="{{ request()->search }}" placeholder="@lang('Search by Plan, price')" id="search" />
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
            <div class="table--responsive">
                <table class="table table-responsive--lg">
                    <thead>
                        <tr>
                            <th>@lang('Plan')</th>
                            <th>@lang('Price')</th>
                            <th>@lang('Website Generation')</th>
                            <th>@lang('Expired Date')</th>
                            <th>@lang('Payment Status')</th>
                            <th>@lang('Purchased At')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscriptions as $subscription)
                            <tr>

                                <td>
                                    <span>{{ $subscription?->plan?->name ?? '' }}</span>
                                </td>


                                <td>
                                    {{ showAmount($subscription->price - $subscription->discount_amount) }}
                                </td>

                                <td>
                                    {{ getAmount($subscription->limit) }}
                                </td>

                                <td>
                                    {{ showDateTime($subscription->expired_date) }} <br> {{ diffForHumans($subscription->expired_date) }}
                                </td>
                                <td>
                                    @php
                                        echo $subscription->paymentStatusBadge;
                                    @endphp
                                </td>
                                <td>
                                    {{ showDateTime($subscription->created_at) }} <br> {{ diffForHumans($subscription->created_at) }}
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
            @if ($subscriptions->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($subscriptions) }}
                </div>
            @endif
        </div>
    </div>
@endsection
