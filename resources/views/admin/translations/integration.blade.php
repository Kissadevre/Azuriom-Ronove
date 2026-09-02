@extends('admin.layouts.admin')

@section('title', $integration->label())

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    <a class="ronove-admin-back" href="{{ route('ronove.admin.translations.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.translations.back_to_center') }}</a>
    @include('ronove::admin._header', ['title' => $integration->label(), 'description' => trans('ronove::admin.translations.integration_description'), 'icon' => str_replace('bi ', '', $integration->icon)])

    @if($providers->count() > 1)
        <ul class="nav nav-tabs mb-4">
            @foreach($providers as $type => $registeredProvider)
                <li class="nav-item">
                    <a class="nav-link @if($type === $provider->type()) active @endif" href="{{ route('ronove.admin.translations.integration', array_filter(['integration' => $integration->id, 'type' => $type, 'locale' => $selectedLocale?->code])) }}" @if($type === $provider->type()) aria-current="page" @endif>
                        {{ $registeredProvider->label() }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="h4 mb-3">{{ $provider->label() }}</h2>

    @if($coverage !== null && $selectedLocale !== null)
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
            <h3 class="h5 mb-0">{{ trans('ronove::admin.translations.coverage_for', ['locale' => $selectedLocale->native_name]) }}</h3>
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
                    <div class="ronove-admin-stat">
                        <div>
                            <div class="text-body-secondary small">{{ trans('ronove::admin.translations.coverage_status.'.$coverageStatus) }}</div>
                            <div class="fs-4 fw-semibold text-{{ $metric['color'] }}">{{ $metric['value'] }}</div>
                        </div><span class="ronove-admin-stat-icon text-{{ $metric['color'] }} bg-{{ $metric['color'] }} bg-opacity-10" aria-hidden="true"><i class="bi bi-bar-chart"></i></span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <form class="ronove-admin-toolbar mb-4" action="{{ route('ronove.admin.translations.integration', $integration->id) }}" method="GET">
        <input type="hidden" name="type" value="{{ $provider->type() }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="coverageLocale">{{ trans('ronove::admin.translations.filters.locale') }}</label>
                <select class="form-select" id="coverageLocale" name="locale">
                    @foreach($locales as $locale)
                        <option value="{{ $locale->code }}" @selected($selectedLocale?->is($locale))>{{ $locale->native_name }}</option>
                    @endforeach
                </select>
            </div>
            @if($filterable)
                <div class="col-md-4 col-xl-{{ $reviewWorkflowEnabled ? '2' : '3' }}">
                    <label class="form-label" for="translationStatus">{{ trans('ronove::admin.translations.filters.status') }}</label>
                    <select class="form-select" id="translationStatus" name="status">
                        <option value="">{{ trans('ronove::admin.translations.filters.all_statuses') }}</option>
                        @foreach(\Azuriom\Plugin\Ronove\Support\TranslationCoverageReport::FILTERS as $filterStatus)
                            <option value="{{ $filterStatus }}" @selected($statusFilter === $filterStatus)>{{ trans('ronove::admin.translations.coverage_status.'.$filterStatus) }}</option>
                        @endforeach
                    </select>
                </div>
                @if($reviewWorkflowEnabled)
                    <div class="col-md-4 col-xl-2">
                        <label class="form-label" for="reviewStatus">{{ trans('ronove::admin.translations.filters.review_status') }}</label>
                        <select class="form-select" id="reviewStatus" name="review_status">
                            <option value="">{{ trans('ronove::admin.translations.filters.all_review_statuses') }}</option>
                            @foreach(\Azuriom\Plugin\Ronove\Models\Translation::REVIEW_STATUSES as $filterReviewStatus)
                                <option value="{{ $filterReviewStatus }}" @selected($reviewStatusFilter === $filterReviewStatus)>{{ trans('ronove::admin.reviews.status.'.$filterReviewStatus) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-4 col-xl-{{ $reviewWorkflowEnabled ? '3' : '4' }}">
                    <label class="form-label" for="resourceSearch">{{ trans('ronove::admin.translations.filters.search') }}</label>
                    <input class="form-control" id="resourceSearch" name="search" value="{{ $searchFilter }}" maxlength="100">
                </div>
            @endif
            <div class="col-xl-2 d-flex gap-2">
                <button class="btn btn-primary" type="submit">{{ trans('ronove::admin.translations.filters.apply') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('ronove.admin.translations.integration', ['integration' => $integration->id, 'type' => $provider->type()]) }}">{{ trans('ronove::admin.translations.filters.clear') }}</a>
            </div>
        </div>
    </form>

    <div class="card ronove-admin-card">
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
                                <strong>{{ $provider->title($resourceModel) }}</strong>
                                <small class="d-block text-body-secondary">{{ $provider->type() }}:{{ $provider->key($resourceModel) }}</small>
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
                                        <span class="badge {{ $isOutdated ? 'text-bg-danger' : ($reviewBadge ?? ($storedTranslation?->isPublished() ? 'text-bg-success' : ($storedTranslation ? 'text-bg-warning' : 'text-bg-secondary'))) }}" @if($isOutdated) title="{{ trans('ronove::admin.translations.outdated_badge', ['locale' => $locale->native_name]) }}" @elseif($reviewWorkflowEnabled && $storedTranslation) title="{{ trans('ronove::admin.reviews.status.'.$storedTranslation->review_status) }}" @endif>
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
                                <a class="btn btn-sm btn-primary" href="{{ route('ronove.admin.translations.edit', array_filter(['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale?->code])) }}">
                                    <i class="bi bi-translate" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ trans('messages.actions.edit') }}</span>
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
