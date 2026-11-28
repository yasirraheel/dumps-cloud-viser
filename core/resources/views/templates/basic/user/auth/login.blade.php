@extends('Template::layouts.app')

@section('app')
    @php
        $loginContent = getContent('login.content', true);
        $bannerContent = getContent('banner.content', true);
        $testimonialElement = getContent('testimonial.element', orderById: true);
        $ratingInitials = $testimonialElement->take(3)->map(function ($testimonial) {
            $name = trim((string) ($testimonial?->data_values?->name ?? ''));
            return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
        });
        $activeLearners = \App\Models\User::active()->count();
        $ratingText = $activeLearners > 0
            ? __('Trusted by :count learners', ['count' => number_format($activeLearners)])
            : __($bannerContent?->data_values?->rating_text ?? '');
    @endphp
    <div class="account-section">
        <div class="account-content">
            <form action="{{ route('user.login') }}" method="POST" class="account-form verify-gcaptcha">
                @csrf
                <a href="{{ route('home') }}" class="logo navbar-brand mb-4">
                    <img class="logo-img" src="{{ siteLogo() }}" alt="Logo">
                </a>

                <div class="form-group">
                    <label class="form--label">@lang('Username')</label>
                    <input class="form--control md-style" name="username" value="{{ old('username') }}" type="text"
                           required>
                </div>

                <div class="form-group">
                    <label class="form--label">@lang('Password')</label>
                    <div class="position-relative">
                        <input type="password" id="password" class="form--control md-style" name="password" autocomplete="off" required>
                        <span class="password-show-hide fas fa-eye toggle-password fa-eye-slash" id="#password"></span>
                    </div>
                </div>

                <div class="flex-between mb-4">
                    <div class="form--check gap-2">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember"
                               {{ old('remember') ? 'checked' : '' }}>
                        <div class="form-check-label">
                            <label for="remember">@lang('Remember Me')</label>
                        </div>
                    </div>
                    <a href="{{ route('user.password.request') }}" class="forgot-password">@lang('Forgot Password?')</a>
                </div>

                <x-captcha label="form--label" class="true" />

                <button type="submit" class="btn btn--md btn--base w-100">@lang('Submit')</button>

                @include('Template::partials.social_login')

                @if (gs('registration'))
                    <p class="account-external text-center mt-4">@lang('Don\'t have an account?')
                        <a href="{{ route('user.register') }}" class="text--base">@lang('Register Now!')</a>
                    </p>
                @endif
            </form>
        </div>

        <div class="account-left">
            <div class="account-left__thumb">
                <img class="fit-image" src="{{ frontendImage('login', $loginContent?->data_values?->image ?? '', '960x945') }}" alt="img">
            </div>

            <div class="account-left__content">
                <div class="account-left__content-top">
                    <div class="account-heading">
                        <h3 class="account-heading__title">{{ __($loginContent?->data_values?->heading ?? '') }}</h3>
                        <p class="account-heading__text">
                            {{ __($loginContent?->data_values?->subheading ?? '') }}
                        </p>
                    </div>

                    <div class="banner-content-rating mb-0">
                        <div class="banner-content-rating-thumb">
                            @foreach ($ratingInitials as $initial)
                                <span class="banner-content-rating-avatar" aria-hidden="true">{{ $initial }}</span>
                            @endforeach
                        </div>
                        <div class="banner-content-rating-content">
                            <div class="banner-content-rating-star">
                                <ul class="star-list">
                                    @php
                                        echo showRatings($bannerContent?->data_values?->review_rating ?? 5);
                                    @endphp
                                </ul>
                                <span class="count">{{ $ratingText }}</span>
                            </div>
                            <p class="banner-content-rating-text">
                                {{ $ratingText }}</p>
                        </div>
                    </div>
                </div>
                <div class="account-left__content-bottom">
                    <div class="testimonial-slider">
                        @foreach ($testimonialElement as $testimonial)
                            @php
                                $testimonialName = trim((string) ($testimonial?->data_values?->name ?? ''));
                                $testimonialInitial = $testimonialName !== '' ? mb_strtoupper(mb_substr($testimonialName, 0, 1)) : '?';
                            @endphp
                            <div class="testimonial">
                                <div class="testimonial-wrapper">
                                    <div class="testimonial-author">
                                        <div class="testimonial-avatar" aria-hidden="true">
                                            {{ $testimonialInitial }}
                                        </div>
                                        <div class="testimonial-person">
                                            <div class="testimonial-name">{{ __($testimonial?->data_values?->name ?? '') }}</div>
                                            <div class="testimonial-job"> {{ __($testimonial?->data_values?->designation ?? '') }}</div>
                                        </div>
                                    </div>
                                    <div class="testimonial-content">
                                        <div class="testimonial-quotemark">
                                            “
                                        </div>
                                        <div class="testimonial-quote">
                                            {{ __($testimonial->data_values?->review ?? '') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
