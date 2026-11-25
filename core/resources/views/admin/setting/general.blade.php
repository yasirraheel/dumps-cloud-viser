@extends('admin.layouts.app')
@section('panel')
    <div class="row mb-none-30">
        <div class="col-lg-12 col-md-12 mb-30">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-xl-3 col-sm-6">
                                <div class="form-group ">
                                    <label> @lang('Site Title')</label>
                                    <input class="form-control" type="text" name="site_name" required value="{{ gs('site_name') }}">
                                </div>
                            </div>
                            <div class="col-xl-3 col-sm-6">
                                <div class="form-group ">
                                    <label>@lang('Currency')</label>
                                    <input class="form-control" type="text" name="cur_text" required value="{{ gs('cur_text') }}">
                                </div>
                            </div>
                            <div class="col-xl-3 col-sm-6">
                                <div class="form-group ">
                                    <label>@lang('Currency Symbol')</label>
                                    <input class="form-control" type="text" name="cur_sym" required value="{{ gs('cur_sym') }}">
                                </div>
                            </div>
                            <div class="form-group col-xl-3 col-sm-6">
                                <label class="required"> @lang('Timezone')</label>
                                <select class="select2 form-control" name="timezone">
                                    @foreach ($timezones as $key => $timezone)
                                        <option value="{{ $key }}" @selected($key == $currentTimezone)>{{ __($timezone) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-xl-3 col-sm-6">
                                <label class="required"> @lang('Site Base Color')</label>
                                <div class="input-group">
                                    <span class="input-group-text p-0 border-0">
                                        <input type='text' class="form-control colorPicker" value="{{ gs('base_color') }}">
                                    </span>
                                    <input type="text" class="form-control colorCode" name="base_color" value="{{ gs('base_color') }}">
                                </div>
                            </div>
                            <div class="form-group col-xl-3 col-sm-6">
                                <label class="required"> @lang('Site Secondary Color')</label>
                                <div class="input-group">
                                    <span class="input-group-text p-0 border-0">
                                        <input type='text' class="form-control colorPicker" value="{{ gs('secondary_color') }}">
                                    </span>
                                    <input type="text" class="form-control colorCode" name="secondary_color" value="{{ gs('secondary_color') }}">
                                </div>
                            </div>
                            <div class="form-group col-xl-3 col-sm-6">
                                <label> @lang('Record to Display Per page')</label>
                                <select class="select2 form-control" name="paginate_number" data-minimum-results-for-search="-1">
                                    <option value="20" @selected(gs('paginate_number') == 20)>@lang('20 items per page')</option>
                                    <option value="50" @selected(gs('paginate_number') == 50)>@lang('50 items per page')</option>
                                    <option value="100" @selected(gs('paginate_number') == 100)>@lang('100 items per page')</option>
                                </select>
                            </div>

                            <div class="form-group col-xl-3 col-sm-6 ">
                                <label class="required"> @lang('Currency Showing Format')</label>
                                <select class="select2 form-control" name="currency_format" data-minimum-results-for-search="-1">
                                    <option value="1" @selected(gs('currency_format') == Status::CUR_BOTH)>@lang('Show Currency Text and Symbol Both')</option>
                                    <option value="2" @selected(gs('currency_format') == Status::CUR_TEXT)>@lang('Show Currency Text Only')</option>
                                    <option value="3" @selected(gs('currency_format') == Status::CUR_SYM)>@lang('Show Currency Symbol Only')</option>
                                </select>
                            </div>


                            <div class="col-xl-6 col-sm-6">
                                <div class="form-group">
                                    <label class="required"> @lang('Select Default Engine for API Key')</label>
                                    <select class="select2 form-control" name="default_engine" data-minimum-results-for-search="-1">
                                        <option value="">@lang('Select one model')</option>
                                        <option value="{{ Status::GEMINI_MODEL }}" @selected(gs('default_engine') == Status::GEMINI_MODEL)>
                                            @lang('Gemini Model')
                                        </option>

                                        <option value="{{ Status::OPENAI_MODEL }}" @selected(gs('default_engine') == Status::OPENAI_MODEL)>
                                            @lang('Openai Model')
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-xl-6 col-sm-6">
                                <div class="form-group ">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                        <label>@lang('Gemini API Key') <i class="las la-info-circle" title="@lang('API Key used to connect with the engine provider. Go to the engine provider website to get the API Key.')"></i></label>
                                        <a href="https://ai.google.dev/gemini-api/docs/text-generation" target="_blank">@lang('Get API Key')</a>
                                    </div>
                                    <input class="form-control" type="text" name="gemini_api_key" required value="{{ gs('gemini_api_key') }}">
                                </div>
                            </div>

                            <div class="col-xl-6 col-sm-6">
                                <div class="form-group ">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                        <label>@lang('OpenAI API Key') <i class="las la-info-circle" title="@lang('API Key used to connect with the engine provider. Go to the engine provider website to get the API Key.')"></i></label>
                                        <a href="https://platform.openai.com/settings/organization/api-keys" target="_blank">@lang('Get API Key')</a>
                                    </div>
                                    <input class="form-control" type="text" name="openai_api_key" required value="{{ gs('openai_api_key') }}">
                                </div>
                            </div>

                            <div class="col-xl-6 col-sm-6">
                                <div class="form-group">
                                    <label class="required"> @lang('Open AI Model')</label>
                                    <select class="select2 form-control" name="openai_api_model" data-minimum-results-for-search="-1">
                                        <option value="">@lang('Select one model')</option>
                                        <option value="gpt-3.5-turbo" @selected(gs('openai_api_model') == 'gpt-3.5-turbo')>@lang('GPT-3.5 Turbo')</option>
                                        <option value="gpt-4.1" @selected(gs('openai_api_model') == 'gpt-4.1')>@lang('GPT-4.1')</option>
                                        <option value="gpt-4.1-mini" @selected(gs('openai_api_model') == 'gpt-4.1-mini')>@lang('GPT-4.1 Mini')</option>
                                        <option value="gpt-4.1-nano" @selected(gs('openai_api_model') == 'gpt-4.1-nano')>@lang('GPT-4.1 Nano')</option>
                                        <option value="gpt-4o" @selected(gs('openai_api_model') == 'gpt-4o')>@lang('GPT-4o')</option>
                                        <option value="gpt-4o-mini" @selected(gs('openai_api_model') == 'gpt-4o-mini')>@lang('GPT-4o Mini')</option>
                                        <option value="o4-mini" @selected(gs('openai_api_model') == 'o4-mini')>@lang('O4 Mini')</option>
                                        <option value="gpt-5" @selected(gs('openai_api_model') == 'gpt-5')>@lang('GPT-5')</option>
                                        <option value="gpt-5-mini" @selected(gs('openai_api_model') == 'gpt-5-mini')>@lang('GPT-5 Mini')</option>
                                        <option value="gpt-5-nano" @selected(gs('openai_api_model') == 'gpt-5-nano')>@lang('GPT-5 Nano')</option>
                                        <option value="gpt-5-pro" @selected(gs('openai_api_model') == 'gpt-5-pro')>@lang('GPT-5 Pro')</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('script-lib')
    <script src="{{ asset('assets/admin/js/spectrum.js') }}"></script>
@endpush

@push('style-lib')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/spectrum.css') }}">
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";


            $('.colorPicker').spectrum({
                color: $(this).data('color'),
                change: function(color) {
                    $(this).parent().siblings('.colorCode').val(color.toHexString().replace(/^#?/, ''));
                }
            });

            $('.colorCode').on('input', function() {
                var clr = $(this).val();
                $(this).parents('.input-group').find('.colorPicker').spectrum({
                    color: clr,
                });
            });
        })(jQuery);
    </script>
@endpush
