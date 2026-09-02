@extends('admin.layouts.admin')

@section('title', $integration->label())

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    <a class="ronove-admin-back" href="{{ route('ronove.admin.translations.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.translations.back_to_center') }}</a>
    @include('ronove::admin._header', ['title' => $integration->label(), 'description' => trans('ronove::admin.translations.integration_description'), 'icon' => str_replace('bi ', '', $integration->icon)])

    @if($providers->count() > 1)
        <div class="ronove-content-tabs mb-4">
        <ul class="nav nav-pills flex-nowrap">
            @foreach($providers as $type => $registeredProvider)
                <li class="nav-item">
                    <a class="nav-link @if($type === $provider->type()) active @endif" href="{{ route('ronove.admin.translations.integration', array_filter(['integration' => $integration->id, 'type' => $type, 'locale' => $selectedLocale?->code])) }}" @if($type === $provider->type()) aria-current="page" @endif>
                        {{ $registeredProvider->label() }}
                    </a>
                </li>
            @endforeach
        </ul>
        </div>
    @endif

    @if($coverage !== null && $selectedLocale !== null)
        <div class="ronove-translation-section-heading">
            <div>
                <span class="ronove-admin-eyebrow">{{ trans('ronove::admin.translations.resource_type') }}</span>
                <h2 class="h4 mb-0">{{ $provider->label() }}</h2>
            </div>
            <span class="ronove-coverage-locale"><i class="bi bi-globe2" aria-hidden="true"></i>{{ trans('ronove::admin.translations.coverage_for', ['locale' => $selectedLocale->native_name]) }}</span>
        </div>
        <div class="row g-3 mb-4">
            @foreach([
                'total' => ['value' => $coverage->total(), 'color' => 'primary'],
                'missing' => ['value' => $coverage->count('missing'), 'color' => 'secondary'],
                'draft' => ['value' => $coverage->count('draft'), 'color' => 'warning'],
                'published' => ['value' => $coverage->count('published'), 'color' => 'success'],
                'outdated' => ['value' => $coverage->count('outdated'), 'color' => 'danger'],
            ] as $coverageStatus => $metric)
                <div class="col-6 col-md">
                    <a class="ronove-admin-stat ronove-coverage-link @if($statusFilter === ($coverageStatus === 'total' ? null : $coverageStatus)) is-active @endif" href="{{ route('ronove.admin.translations.integration', array_filter(['integration' => $integration->id, 'type' => $provider->type(), 'locale' => $selectedLocale->code, 'status' => $coverageStatus === 'total' ? null : $coverageStatus])) }}" @if(!$filterable) aria-disabled="true" tabindex="-1" @endif>
                        <div>
                            <span class="ronove-admin-stat-label">{{ trans('ronove::admin.translations.coverage_status.'.$coverageStatus) }}</span>
                            <strong class="ronove-admin-stat-value text-{{ $metric['color'] }}">{{ $metric['value'] }}</strong>
                        </div><span class="ronove-admin-stat-icon text-{{ $metric['color'] }} bg-{{ $metric['color'] }} bg-opacity-10" aria-hidden="true"><i class="bi bi-bar-chart"></i></span>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <form class="ronove-admin-toolbar mb-4" action="{{ route('ronove.admin.translations.integration', $integration->id) }}" method="GET">
        <input type="hidden" name="type" value="{{ $provider->type() }}">
        <div class="ronove-admin-toolbar-title"><i class="bi bi-funnel" aria-hidden="true"></i>{{ trans('ronove::admin.translations.filters.title') }}</div>
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-{{ $filterable ? ($reviewWorkflowEnabled ? '2' : '3') : '9' }}">
                <label class="form-label" for="coverageLocale">{{ trans('ronove::admin.translations.filters.locale') }}</label>
                <select class="form-select" id="coverageLocale" name="locale">
                    @foreach($locales as $locale)
                        <option value="{{ $locale->code }}" @selected($selectedLocale?->is($locale))>{{ $locale->native_name }}</option>
                    @endforeach
                </select>
            </div>
            @if($filterable)
                <div class="col-md-6 col-lg-{{ $reviewWorkflowEnabled ? '2' : '3' }}">
                    <label class="form-label" for="translationStatus">{{ trans('ronove::admin.translations.filters.status') }}</label>
                    <select class="form-select" id="translationStatus" name="status">
                        <option value="">{{ trans('ronove::admin.translations.filters.all_statuses') }}</option>
                        @foreach(\Azuriom\Plugin\Ronove\Support\TranslationCoverageReport::FILTERS as $filterStatus)
                            <option value="{{ $filterStatus }}" @selected($statusFilter === $filterStatus)>{{ trans('ronove::admin.translations.coverage_status.'.$filterStatus) }}</option>
                        @endforeach
                    </select>
                </div>
                @if($reviewWorkflowEnabled)
                    <div class="col-md-6 col-lg-2">
                        <label class="form-label" for="reviewStatus">{{ trans('ronove::admin.translations.filters.review_status') }}</label>
                        <select class="form-select" id="reviewStatus" name="review_status">
                            <option value="">{{ trans('ronove::admin.translations.filters.all_review_statuses') }}</option>
                            @foreach(\Azuriom\Plugin\Ronove\Models\Translation::REVIEW_STATUSES as $filterReviewStatus)
                                <option value="{{ $filterReviewStatus }}" @selected($reviewStatusFilter === $filterReviewStatus)>{{ trans('ronove::admin.reviews.status.'.$filterReviewStatus) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-6 col-lg-3">
                    <label class="form-label" for="resourceSearch">{{ trans('ronove::admin.translations.filters.search') }}</label>
                    <input class="form-control" id="resourceSearch" name="search" value="{{ $searchFilter }}" maxlength="100">
                </div>
            @endif
            <div class="col-md-6 col-lg-3 ronove-admin-filter-actions">
                <button class="btn btn-primary" type="submit">{{ trans('ronove::admin.translations.filters.apply') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('ronove.admin.translations.integration', ['integration' => $integration->id, 'type' => $provider->type()]) }}">{{ trans('ronove::admin.translations.filters.clear') }}</a>
            </div>
        </div>
    </form>

    <div class="card ronove-admin-card">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div>
                <span class="ronove-admin-eyebrow">{{ $provider->label() }}</span>
                <h3 class="h5 mb-0">{{ trans('ronove::admin.translations.resources_title') }}</h3>
            </div>
            <span class="ronove-glossary-count">{{ $resources->total() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table ronove-admin-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('ronove::admin.translations.resource') }}</th>
                        <th>{{ trans('ronove::admin.translations.coverage') }}</th>
                        <th class="text-end">{{ trans('messages.actions.edit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resources as $resourceModel)
                        @php($resourceKey = $provider->key($resourceModel))
                        @php($storedResource = $storedResources->get($provider->key($resourceModel)))
                        <tr>
                            <td>
                                <div class="ronove-resource-cell">
                                    <span class="ronove-resource-icon" aria-hidden="true"><i class="bi bi-file-text"></i></span>
                                    <div><strong>{{ $provider->title($resourceModel) }}</strong><small>{{ $provider->type() }}:{{ $provider->key($resourceModel) }}</small></div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($locales as $locale)
                                        @php($storedTranslation = $storedResource?->translations?->firstWhere('locale_id', $locale->id))
                                        @php($isOutdated = $selectedLocale?->is($locale) && $coverage?->isOutdated($resourceKey))
                                        @php($reviewBadge = $reviewWorkflowEnabled && $storedTranslation
                                            ? match ($storedTranslation->review_status) {
                                                'pending' => 'text-bg-info',
                                                'changes_requested' => 'text-bg-danger',
                                                'approved' => 'text-bg-success',
                                                default => 'text-bg-warning',
                                            }
                                            : null)
                                        <span class="badge rounded-pill {{ $isOutdated ? 'text-bg-danger' : ($reviewBadge ?? ($storedTranslation?->isPublished() ? 'text-bg-success' : ($storedTranslation ? 'text-bg-warning' : 'text-bg-secondary'))) }}" title="{{ $isOutdated ? trans('ronove::admin.translations.outdated_badge', ['locale' => $locale->native_name]) : ($reviewWorkflowEnabled && $storedTranslation ? trans('ronove::admin.reviews.status.'.$storedTranslation->review_status) : $locale->native_name) }}">
                                            {{ $locale->code }}
                                            @if($isOutdated)
                                                <i class="bi bi-exclamation-triangle ms-1" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ trans('ronove::admin.translations.outdated_badge', ['locale' => $locale->native_name]) }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary text-nowrap" href="{{ route('ronove.admin.translations.edit', array_filter(['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale?->code])) }}">
                                    <i class="bi bi-translate me-xl-1" aria-hidden="true"></i>
                                    <span class="d-none d-xl-inline">{{ trans('messages.actions.edit') }}</span>
                                    <span class="visually-hidden d-xl-none">{{ trans('messages.actions.edit') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3"><div class="ronove-admin-empty"><span class="ronove-admin-empty-icon"><i class="bi bi-search"></i></span><strong>{{ trans('ronove::admin.translations.empty') }}</strong></div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $resources->links() }}</div>
    </div>
@endsection
