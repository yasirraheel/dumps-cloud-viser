@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-md-12">
            <div class="card overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive--sm">
                        <table class="align-items-center table--light table">
                            <thead>
                                <tr>
                                    <th>@lang('Short Code') </th>
                                    <th>@lang('Description')</th>
                                </tr>
                            </thead>
                            <tbody class="list">
                                <tr>
                                    <td><span class="short-codes">@{{ site_logo }}</span></td>
                                    <td>@lang('Current logo from General Settings')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ site_name }}</span></td>
                                    <td>@lang('Name of your site')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ fullname }}</span></td>
                                    <td>@lang('Full Name of User')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ exam_category }}</span></td>
                                    <td>@lang('Exam category')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ exam_title }}</span></td>
                                    <td>@lang('Exam bank title')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ exam_score }}</span></td>
                                    <td>@lang('Final exam score percentage')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ exam_based_message }}</span></td>
                                    <td>@lang('Message')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ exam_completion_date }}</span></td>
                                    <td>@lang('Date the exam was completed')</td>
                                </tr>
                                <tr>
                                    <td><span class="short-codes">@{{ qr_code }}</span></td>
                                    <td>@lang('QR code for certificate verification')</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card mt-5">
                <div class="card-body">
                    <form action="{{ route('admin.certificate.template.update') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Certificate Verify Url')</label>
                                    <input class="form-control" placeholder="@lang('https://www.example.com/verify-certificate')" name="verify_url" value="{{ gs('verify_url') }}" required>
                                    <small class="text--primary"><i class="las la-info-circle"></i>@lang('The link should be ')https://yourfrontendsite.com/verify-certificate</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Email Body') </label>
                                    <textarea name="certificate_template" rows="20" class="form-control certificateTemplateEditor" id="htmlInput" placeholder="@lang('Your Certificate template')">{{ gs('certificate_template') }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="previewContainer">
                                    <label>&nbsp;</label>
                                    <iframe id="iframePreview"></iframe>
                                </div>
                            </div>
                        </div>
                        <button class="btn w-100 btn--primary h-45" type="submit">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('style')
    <style>
        #iframePreview {
            width: 100%;
            height: 600px;
            border: none;
        }

        .certificateTemplateEditor {
            min-height: 600px;
        }
    </style>
@endpush
@push('script')
    <script>
        var iframe = document.getElementById('iframePreview');
        var certificatePreviewValues = {
            site_logo: '<img src="' + @json(siteLogo()) + '" style="width:170px;height:auto;">',
            site_name: @json(gs('site_name')),
            fullname: 'Sample Candidate',
            exam_category: @json($previewExam?->category?->name ?? 'Exam category'),
            exam_title: @json($previewExam?->title ?? 'Exam bank title'),
            exam_score: '96%',
            exam_based_message: 'You achieved 96% in your exam. Well done!',
            exam_completion_date: @json(now()->format('F j, Y')),
            qr_code: '<div style="width:64px;height:64px;border:4px solid #172642;background:#fff;text-align:center;line-height:56px;color:#172642;font-weight:bold;">QR</div>'
        };
        var certificateShortcode = function(name) {
            return '{' + '{' + name + '}' + '}';
        };
        var renderCertificatePreview = function() {
            var htmlContent = document.getElementById('htmlInput').value;
            Object.keys(certificatePreviewValues).forEach(function(name) {
                htmlContent = htmlContent.split(certificateShortcode(name)).join(certificatePreviewValues[name]);
            });
            iframe.src = 'data:text/html;charset=utf-8,' + encodeURIComponent(htmlContent);
        };
        $(".certificateTemplateEditor").on('input', renderCertificatePreview).trigger('input');
    </script>
@endpush
