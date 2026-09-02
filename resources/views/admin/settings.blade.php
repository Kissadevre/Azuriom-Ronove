@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.settings.title'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    @include('ronove::admin._header', [
        'title' => trans('ronove::admin.settings.title'),
        'description' => trans('ronove::admin.settings.description'),
        'icon' => 'bi-sliders',
    ])

    <form action="{{ route('ronove.admin.settings.update') }}" method="POST">
        @csrf
        <input type="hidden" name="review_workflow_enabled" value="0">

        <div class="card ronove-admin-card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-arrow-repeat text-primary" aria-hidden="true"></i>
                <h2 class="h5 mb-0">{{ trans('ronove::admin.settings.review_workflow') }}</h2>
            </div>
            <div class="card-body">
                <div class="ronove-setting-row">
                    <div>
                        <label class="fw-semibold d-block" for="reviewWorkflowEnabled">{{ trans('ronove::admin.settings.review_workflow') }}</label>
                        <p class="text-body-secondary small mt-1 mb-0">{{ trans('ronove::admin.settings.review_workflow_help') }}</p>
                    </div>
                    <div class="form-check form-switch">
                    <input class="form-check-input @error('review_workflow_enabled') is-invalid @enderror" id="reviewWorkflowEnabled" type="checkbox" name="review_workflow_enabled" value="1" @checked(old('review_workflow_enabled', $reviewWorkflowEnabled))>
                    <label class="visually-hidden" for="reviewWorkflowEnabled">{{ trans('ronove::admin.settings.review_workflow') }}</label>
                    @error('review_workflow_enabled')
                        <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                    @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-clock-history me-1" aria-hidden="true"></i>
            {{ trans('ronove::admin.settings.revisions_help') }}
        </div>

        <div class="ronove-sticky-actions">
            <button class="btn btn-primary ms-auto" type="submit"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ trans('messages.actions.save') }}</button>
        </div>
    </form>
    </div>
@endsection
