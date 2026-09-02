@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.settings.title'))

@include('ronove::admin._assets')

@push('footer-scripts')
    <script>
        (() => {
            const toggle = document.getElementById('reviewWorkflowEnabled');
            const status = document.getElementById('ronoveWorkflowStatus');
            const mode = document.getElementById('ronoveWorkflowMode');
            const direct = document.getElementById('ronoveDirectMode');
            const review = document.getElementById('ronoveReviewMode');
            const publicPageToggle = document.getElementById('publicLanguagePageEnabled');
            const publicPageStatus = document.getElementById('ronovePublicPageStatus');

            if (! toggle || ! publicPageToggle) return;

            const refresh = () => {
                status.textContent = toggle.checked ? @json(trans('ronove::admin.settings.enabled')) : @json(trans('ronove::admin.settings.disabled'));
                status.className = `badge rounded-pill text-bg-${toggle.checked ? 'success' : 'secondary'}`;
                mode.textContent = toggle.checked ? @json(trans('ronove::admin.settings.review_mode')) : @json(trans('ronove::admin.settings.direct_mode'));
                direct.classList.toggle('is-active', ! toggle.checked);
                review.classList.toggle('is-active', toggle.checked);
                publicPageStatus.textContent = publicPageToggle.checked ? @json(trans('ronove::admin.settings.enabled')) : @json(trans('ronove::admin.settings.disabled'));
                publicPageStatus.className = `badge rounded-pill text-bg-${publicPageToggle.checked ? 'success' : 'secondary'}`;
            };

            toggle.addEventListener('change', refresh);
            publicPageToggle.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endpush

@section('content')
    <div class="ronove-admin-shell">
    @include('ronove::admin._header', [
        'title' => trans('ronove::admin.settings.title'),
        'description' => trans('ronove::admin.settings.description'),
        'icon' => 'bi-sliders',
    ])

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="ronove-admin-stat"><div><span class="ronove-admin-stat-label">{{ trans('ronove::admin.settings.workflow_status') }}</span><strong class="ronove-admin-stat-value fs-5"><span class="badge rounded-pill" id="ronoveWorkflowStatus"></span></strong></div><span class="ronove-admin-stat-icon text-primary bg-primary bg-opacity-10"><i class="bi bi-arrow-repeat"></i></span></div></div>
        <div class="col-md-4"><div class="ronove-admin-stat"><div><span class="ronove-admin-stat-label">{{ trans('ronove::admin.settings.current_mode') }}</span><strong class="ronove-admin-stat-value fs-5" id="ronoveWorkflowMode"></strong></div><span class="ronove-admin-stat-icon text-info bg-info bg-opacity-10"><i class="bi bi-signpost-split"></i></span></div></div>
        <div class="col-md-4"><div class="ronove-admin-stat"><div><span class="ronove-admin-stat-label">{{ trans('ronove::admin.settings.revision_history') }}</span><strong class="ronove-admin-stat-value fs-5">{{ trans('ronove::admin.settings.always_active') }}</strong></div><span class="ronove-admin-stat-icon text-success bg-success bg-opacity-10"><i class="bi bi-clock-history"></i></span></div></div>
    </div>

    <form action="{{ route('ronove.admin.settings.update') }}" method="POST">
        @csrf
        <input type="hidden" name="review_workflow_enabled" value="0">
        <input type="hidden" name="public_language_page_enabled" value="0">

        <div class="card ronove-admin-card mb-4">
            <div class="card-header">
                <span class="ronove-admin-eyebrow">{{ trans('ronove::admin.settings.translation_workflow') }}</span>
                <h2 class="h5 mb-1">{{ trans('ronove::admin.settings.review_workflow') }}</h2>
                <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.settings.workflow_description') }}</p>
            </div>
            <div class="card-body">
                <div class="ronove-setting-row">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ronove-setting-icon text-primary bg-primary bg-opacity-10" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>
                            <label class="fw-semibold" for="reviewWorkflowEnabled">{{ trans('ronove::admin.settings.review_workflow') }}</label>
                        </div>
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

                <div class="ronove-workflow-modes mt-3">
                    <div class="ronove-workflow-mode" id="ronoveDirectMode">
                        <span class="ronove-setting-icon text-secondary bg-secondary bg-opacity-10"><i class="bi bi-lightning-charge"></i></span>
                        <div><strong>{{ trans('ronove::admin.settings.direct_mode') }}</strong><p>{{ trans('ronove::admin.settings.direct_mode_help') }}</p></div>
                        <i class="bi bi-check-circle-fill ronove-workflow-mode-check" aria-hidden="true"></i>
                    </div>
                    <div class="ronove-workflow-mode" id="ronoveReviewMode">
                        <span class="ronove-setting-icon text-primary bg-primary bg-opacity-10"><i class="bi bi-person-check"></i></span>
                        <div><strong>{{ trans('ronove::admin.settings.review_mode') }}</strong><p>{{ trans('ronove::admin.settings.review_mode_help') }}</p></div>
                        <i class="bi bi-check-circle-fill ronove-workflow-mode-check" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="ronove-settings-note mt-3"><i class="bi bi-clock-history" aria-hidden="true"></i><span>{{ trans('ronove::admin.settings.revisions_help') }}</span></div>
            </div>
        </div>

        <div class="card ronove-admin-card mb-4">
            <div class="card-header">
                <span class="ronove-admin-eyebrow">{{ trans('ronove::admin.settings.visitor_experience') }}</span>
                <h2 class="h5 mb-1">{{ trans('ronove::admin.settings.public_language_page') }}</h2>
                <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.settings.public_language_page_description') }}</p>
            </div>
            <div class="card-body">
                <div class="ronove-setting-row">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ronove-setting-icon text-info bg-info bg-opacity-10" aria-hidden="true"><i class="bi bi-window"></i></span>
                            <label class="fw-semibold" for="publicLanguagePageEnabled">{{ trans('ronove::admin.settings.public_language_page') }}</label>
                            <span class="badge rounded-pill" id="ronovePublicPageStatus"></span>
                        </div>
                        <p class="text-body-secondary small mt-1 mb-0">{{ trans('ronove::admin.settings.public_language_page_help') }}</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input @error('public_language_page_enabled') is-invalid @enderror" id="publicLanguagePageEnabled" type="checkbox" name="public_language_page_enabled" value="1" @checked(old('public_language_page_enabled', $publicLanguagePageEnabled))>
                        <label class="visually-hidden" for="publicLanguagePageEnabled">{{ trans('ronove::admin.settings.public_language_page') }}</label>
                        @error('public_language_page_enabled')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>
                </div>
                <div class="ronove-settings-note mt-3"><i class="bi bi-code-slash" aria-hidden="true"></i><span>{{ trans('ronove::admin.settings.public_language_page_endpoint_help') }}</span></div>
            </div>
        </div>

        <div class="ronove-sticky-actions">
            <span class="small text-body-secondary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ trans('ronove::admin.settings.save_hint') }}</span>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ trans('messages.actions.save') }}</button>
        </div>
    </form>
    </div>
@endsection
