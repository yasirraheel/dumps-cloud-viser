@php
    $pricingContent = getContent('pricing.content', true);
    $plans = App\Models\Plan::active()->orderBy('id')->get();
@endphp



<section class="pricing-section my-120" id="pricing-plans">
    <div class="container">
        <div class="section-heading mb-lg-5 wow fadeInDown" data-wow-delay="0.2s">
            <h2 class="section-heading__title" data-highlight-start="{{ $pricingContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $pricingContent?->data_values?->highlight_end ?? 3 }}">
                {{ __($pricingContent?->data_values?->subheading ?? '') }}
            </h2>
            <p class="section-heading__desc">
                {{ __($pricingContent?->data_values?->short_description ?? '') }}
            </p>
        </div>

        <div class="pricing-billing-priod wow fadeInDown" data-wow-delay="0.2s">
            <span class="pricing-billing-priod__label">@lang('Monthly')</span>
            <div class="form--switch">
                <input class="form-check-input plan-interval" type="checkbox" />
            </div>
            <span class="pricing-billing-priod__label">@lang('Annual')</span>
        </div>

        <div class="row gy-4">
            @foreach ($plans as $plan)
                @php
                    $planCategories = $plan->accessCategories();
                    $planExams = $plan->accessExams();
                    $isCurrentPlan = auth()->check()
                        && (int) auth()->user()->plan_id === (int) $plan->id
                        && (!auth()->user()->expired_date || auth()->user()->expired_date >= now()->format('Y-m-d'));
                    $categoryNames = $planCategories->pluck('name')->map(fn ($name) => __($name))->implode(', ');
                    $bankNames = $planExams->pluck('title')->map(fn ($title) => __($title))->implode(', ');
                @endphp
                <div class="col-lg-4 col-sm-6 flex-grow-1">
                    <div class="pricing-card wow fadeInDown @if ($isCurrentPlan) base-style @endif" data-wow-delay="0.2s">
                        @if ($isCurrentPlan)
                            <div class="pricing-card__base">@lang('Expire this after') {{ diffForHumans(auth()->user()->expired_date) }}</div>
                        @endif
                        <div class="pricing-card__top">
                            <h5 class="pricing-card__title gradient-text">
                                {{ __($plan->name) }}
                            </h5>
                            <div class="pricing-card-pricing">

                                <h2 class="pricing-card-pricing__amount monthly-price">
                                    {{ gs('cur_sym') }}{{ showAmount($plan->monthly_price, currencyFormat: false) }}
                                    <span>/ @lang('Month')</span>
                                </h2>
                                <h2 class="pricing-card-pricing__amount yearly-price d-none">
                                    {{ gs('cur_sym') }}{{ showAmount($plan->yearly_price, currencyFormat: false) }}
                                    <span>/ @lang('Year')</span>
                                </h2>
                            </div>
                            <p class="pricing-card__for">{{ __($plan->short_description) }}</p>
                        </div>

                        <ul class="pricing-list">
                            <li class="pricing-list__item">
                                <p class="text monthly-credit">
                                    <span class="fw-semibold">{{ getAmount($plan->monthly_exam) }}</span> @lang('monthly attempts')
                                </p>
                                <p class="text yearly-credit d-none">
                                    <span class="fw-semibold">{{ getAmount($plan->yearly_exam) }}</span> @lang('yearly attempts')
                                </p>
                            </li>
                            <li class="pricing-list__item {{ $categoryNames ? '' : 'is-locked' }}">
                                <p class="text"><span class="fw-semibold">@lang('Categories'):</span> {{ $categoryNames ?: __('Not included') }}</p>
                            </li>
                            <li class="pricing-list__item {{ $bankNames ? '' : 'is-locked' }}">
                                <p class="text"><span class="fw-semibold">@lang('Exam banks'):</span> {{ $bankNames ?: __('Not included') }}</p>
                            </li>
                            <li class="pricing-list__item {{ $plan->allowsSimulation() ? '' : 'is-locked' }}">
                                <p class="text">@lang('Simulation mode')</p>
                            </li>
                            <li class="pricing-list__item {{ $plan->hasFeature('answer_explanations') ? '' : 'is-locked' }}">
                                <p class="text">@lang('Answer explanations')</p>
                            </li>
                            <li class="pricing-list__item {{ $plan->hasFeature('progress_tracking') ? '' : 'is-locked' }}">
                                <p class="text">@lang('Progress tracking')</p>
                            </li>
                            <li class="pricing-list__item {{ $plan->hasFeature('certificate') ? '' : 'is-locked' }}">
                                <p class="text">@lang('Completion certificate')</p>
                            </li>
                        </ul>

                        @if ($isCurrentPlan)
                            <span class="btn btn--md btn--base w-100">@lang('Current plan')</span>
                        @elseif ($plan->is_free)
                            @auth
                                <button class="btn btn--md btn--base w-100 subscribeBtn" type="submit" data-plan_id="{{ $plan->id }}">@lang('Start free')</button>
                            @else
                                <a class="btn btn--md btn--base w-100" href="{{ route('user.register') }}">@lang('Create free account')</a>
                            @endauth
                        @elseif (auth()->check())
                            <button class="btn btn--md btn--base w-100 subscribeBtn" type="submit" data-plan_id="{{ $plan->id }}">@lang('Subscribe')</button>
                        @else
                            <button class="btn btn--md btn--base w-100 loginBtn" type="button">@lang('View plan')</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>





<div class="modal custom--modal fade" id="loginModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">@lang('Login Required to Subscribe')</h6>
                <button class="btn btn--close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <p class="text-center">@lang('Please log in to your account to subscribe to a plan and unlock full access to all features.')</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--danger btn--sm" data-bs-dismiss="modal">@lang('Close')</button>
                <a href="{{ route('user.login') }}" class="btn btn--base btn--sm">@lang('Login')</a>
            </div>
        </div>
    </div>
</div>

<div class="modal custom--modal fade" id="subscriptionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">@lang('Confirm Subscription!')</h6>
                <button class="btn btn--close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-center">@lang('Are you sure to subscribe this plan?')</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--danger btn--sm" data-bs-dismiss="modal">@lang('No')</button>
                    <button type="submit" class="btn btn--base btn--sm">@lang('Yes')</button>
                </div>
            </form>
        </div>
    </div>
</div>




@push('script')
    <script>
        (function($) {
            "use strict";

            let monthlyPrice = $('.monthly-price');
            let yearlyPrice = $('.yearly-price');
            let monthlyCredit = $('.monthly-credit');
            let yearlyCredit = $('.yearly-credit');
            let monthlyExpired = $('.monthly-expired');
            let yearlyExpired = $('.yearly-expired');


            $('.plan-interval').on('change', function() {
                if ($(this).prop('checked')) {
                    monthlyPrice.addClass('d-none')
                    monthlyCredit.addClass('d-none')
                    monthlyExpired.addClass('d-none')
                    yearlyPrice.removeClass('d-none')
                    yearlyCredit.removeClass('d-none')
                    yearlyExpired.removeClass('d-none')
                } else {
                    monthlyPrice.removeClass('d-none')
                    monthlyCredit.removeClass('d-none')
                    monthlyExpired.removeClass('d-none')
                    yearlyPrice.addClass('d-none')
                    yearlyCredit.addClass('d-none')
                    yearlyExpired.addClass('d-none')
                }
            }).change();

            $('.subscribeBtn').on('click', function(e) {
                e.preventDefault();
                let planInterval = $('.plan-interval').prop('checked') ? 1 : 0;
                let route = `{{ route('user.subscribe.plan', '') }}/${$(this).data('plan_id')}/${planInterval}`;
                let subscribeModal = $('#subscriptionModal');
                subscribeModal.find('form').attr('action', route);
                subscribeModal.modal('show');
            });
            $('.loginBtn').on('click', function(e) {
                e.preventDefault();
                let modal = $('#loginModal');
                modal.modal('show');
            });
        })(jQuery)
    </script>
@endpush
