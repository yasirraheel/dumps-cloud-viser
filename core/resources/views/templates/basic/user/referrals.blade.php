@extends('Template::layouts.master')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card custom--card">
                        <div class="card-body">
                            <h4 class="mb-1">@lang('Refer & Enjoy the Bonus')</h4>
                            <p class="mb-3">@lang('You\'ll get commission against your referral\'s activities. Level has been decided by the') <strong><i>{{ __(gs('site_name')) }}</i></strong> @lang('authority. If you reach the level, you\'ll get commission.')</p>
                            <div class="input-group">
                                <input type="text" class="form-control form--control copyURL" value="{{ route('home') }}?reference={{ auth()->user()->username }}" readonly>
                                <button type="button" class="input-group-text copyBoard" id="copyBoard">
                                    <i class="las la-copy"></i> <strong class="copyText">@lang('Copy')</strong>
                                </button>
                            </div>

                        </div>
                    </div>
                    @if ($user->allReferrals->count() > 0 && $maxLevel > 0)
                        <div class="card custom--card mt-4">
                            <div class="card-body">
                                <div class="treeview-container">
                                    <ul class="treeview">
                                        <li class="items-expanded"> {{ $user->fullname }} ( {{ $user->username }} )
                                            @include('Template::partials.under_tree', ['user' => $user, 'layer' => 0, 'isFirst' => true])
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@push('style')
    <link href="{{ asset('assets/global/css/jquery.treeView.css') }}" rel="stylesheet" type="text/css">
@endpush
@push('script')
    <script src="{{ asset('assets/global/js/jquery.treeView.js') }}"></script>
    <script>
        (function($) {
            "use strict"
            $('.treeview').treeView();
            $('.copyBoard').on('click', function() {
                var copyText = document.getElementsByClassName("copyURL");
                copyText = copyText[0];
                copyText.select();
                copyText.setSelectionRange(0, 99999);

                document.execCommand("copy");
                $('.copyText').text('Copied');
                notify('success', 'Referred Link Copied');
                setTimeout(() => {
                    $('.copyText').text('Copy');
                }, 2000);
            });
        })(jQuery);
    </script>
@endpush
