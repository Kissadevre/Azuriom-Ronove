@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.settings.title'))

@section('content')
    <div class="mb-4">
        <h1 class="mb-1">{{ trans('ronove::admin.settings.title') }}</h1>
        <p class="text-body-secondary mb-0">{{ trans('ronove::admin.settings.description') }}</p>
    </div>

    <form action="{{ route('ronove.admin.settings.update') }}" method="POST">
        @csrf
        <input type="hidden" name="review_workflow_enabled" value="0">

        <div class="card mb-4">
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input @error('review_workflow_enabled') is-invalid @enderror" id="reviewWorkflowEnabled" type="checkbox" name="review_workflow_enabled" value="1" @checked(old('review_workflow_enabled', $reviewWorkflowEnabled))>
                    <label class="form-check-label fw-semibold" for="reviewWorkflowEnabled">
                        {{ trans('ronove::admin.settings.review_workflow') }}
                    </label>
                    @error('review_workflow_enabled')
                        <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
                <p class="text-body-secondary mt-2 mb-0">{{ trans('ronove::admin.settings.review_workflow_help') }}</p>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-clock-history me-1" aria-hidden="true"></i>
            {{ trans('ronove::admin.settings.revisions_help') }}
        </div>

        <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1" aria-hidden="true"></i>{{ trans('messages.actions.save') }}
        </button>
    </form>
@endsection
