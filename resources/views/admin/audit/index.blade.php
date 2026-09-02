@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.audit.title'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
        @can('ronove.translations')
            <a class="ronove-admin-back" href="{{ route('ronove.admin.translations.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.audit.back') }}</a>
        @endcan

        @include('ronove::admin._header', [
            'title' => trans('ronove::admin.audit.title'),
            'description' => trans('ronove::admin.audit.description'),
            'icon' => 'bi-shield-check',
            'actions' => [['label' => trans('messages.actions.refresh'), 'url' => route('ronove.admin.audit.index'), 'icon' => 'bi-arrow-clockwise']],
        ])

        <section class="card ronove-admin-card ronove-audit-overview mb-4" aria-labelledby="ronove-audit-summary">
            <div class="card-header ronove-audit-overview-header">
                <div>
                    <span class="ronove-admin-eyebrow">{{ trans('ronove::admin.audit.report_eyebrow') }}</span>
                    <h2 class="h5 mb-1" id="ronove-audit-summary">{{ trans('ronove::admin.audit.report_title') }}</h2>
                    <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.audit.scan_notice') }}</p>
                </div>
                <span class="ronove-audit-scan-badge {{ $report->isHealthy() ? 'is-healthy' : 'has-findings' }}">
                    <i class="bi {{ $report->isHealthy() ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' }}" aria-hidden="true"></i>
                    {{ $report->isHealthy() ? trans('ronove::admin.audit.scan_complete') : trans('ronove::admin.audit.attention_required') }}
                </span>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    @foreach([
                        ['value' => $report->count(), 'label' => trans('ronove::admin.audit.total'), 'color' => 'primary', 'icon' => 'bi-search'],
                        ['value' => $report->cleanableCount(), 'label' => trans('ronove::admin.audit.cleanable'), 'color' => 'warning', 'icon' => 'bi-tools'],
                        ['value' => $report->informationalCount(), 'label' => trans('ronove::admin.audit.informational'), 'color' => 'info', 'icon' => 'bi-eye'],
                    ] as $metric)
                        <div class="col-md-4">
                            <div class="ronove-audit-metric">
                                <span class="ronove-admin-stat-icon text-{{ $metric['color'] }} bg-{{ $metric['color'] }} bg-opacity-10" aria-hidden="true"><i class="bi {{ $metric['icon'] }}"></i></span>
                                <div>
                                    <strong class="ronove-admin-stat-value text-{{ $metric['color'] }}">{{ $metric['value'] }}</strong>
                                    <span class="ronove-admin-stat-label">{{ $metric['label'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if($report->isHealthy())
            <section class="card ronove-admin-card ronove-audit-healthy">
                <div class="card-body ronove-audit-healthy-body">
                    <span class="ronove-audit-healthy-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    <div class="flex-grow-1">
                        <span class="ronove-admin-eyebrow text-success">{{ trans('ronove::admin.audit.healthy_eyebrow') }}</span>
                        <h2 class="h4 mb-2">{{ trans('ronove::admin.audit.healthy') }}</h2>
                        <p class="text-body-secondary mb-3">{{ trans('ronove::admin.audit.healthy_help') }}</p>
                        <div class="ronove-audit-scope" aria-label="{{ trans('ronove::admin.audit.checked_areas') }}">
                            @foreach(['resources', 'translations', 'notes', 'glossary', 'preferences', 'fallbacks'] as $area)
                                <span><i class="bi bi-check2" aria-hidden="true"></i>{{ trans('ronove::admin.audit.areas.'.$area) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @else
            <div class="ronove-audit-guidance mb-4">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <div><strong>{{ trans('ronove::admin.audit.cleanup_guidance_title') }}</strong><p class="mb-0">{{ trans('ronove::admin.audit.cleanup_notice') }}</p></div>
            </div>

            <nav class="ronove-audit-category-nav mb-4" aria-label="{{ trans('ronove::admin.audit.finding_categories') }}">
                @foreach($categories as $category)
                    @php($categoryCount = $report->countFor($category))
                    @continue($categoryCount === 0)
                    <a href="#audit-{{ $category }}"><span>{{ trans('ronove::admin.audit.categories.'.$category.'.title') }}</span><strong>{{ $categoryCount }}</strong></a>
                @endforeach
            </nav>

            @foreach($categories as $category)
                @php($categoryIssues = $report->issuesFor($category))
                @continue($categoryIssues->isEmpty())
                @php($cleanable = $categoryIssues->first()->isCleanable())

                <section class="card ronove-admin-card ronove-audit-category mb-4" id="audit-{{ $category }}">
                    <div class="card-header ronove-audit-category-header">
                        <div class="ronove-audit-category-title">
                            <span class="ronove-setting-icon {{ $cleanable ? 'text-warning bg-warning' : 'text-info bg-info' }} bg-opacity-10" aria-hidden="true"><i class="bi {{ $cleanable ? 'bi-tools' : 'bi-eye' }}"></i></span>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h2 class="h5 mb-0">{{ trans('ronove::admin.audit.categories.'.$category.'.title') }}</h2>
                                    <span class="badge rounded-pill {{ $cleanable ? 'text-bg-warning' : 'text-bg-info' }}">{{ $categoryIssues->count() }}</span>
                                </div>
                                <p class="text-body-secondary mb-0">{{ trans('ronove::admin.audit.categories.'.$category.'.description') }}</p>
                            </div>
                        </div>
                        @unless($cleanable)
                            <span class="ronove-audit-mode-badge text-info bg-info bg-opacity-10"><i class="bi bi-person-check" aria-hidden="true"></i>{{ trans('ronove::admin.audit.manual_only') }}</span>
                        @endunless
                    </div>

                    <div class="list-group list-group-flush">
                        @foreach($categoryIssues->take(50) as $issue)
                            <div class="list-group-item ronove-audit-finding">
                                <span class="ronove-audit-record-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <strong class="text-break">{{ $issue->reference }}</strong>
                                        <span class="badge text-bg-secondary">{{ trans('ronove::admin.audit.record_types.'.$issue->recordType) }}</span>
                                        @if($issue->locale)<span class="badge text-bg-light border">{{ $issue->locale }}</span>@endif
                                    </div>
                                    @if($issue->details)
                                        <div class="small text-body-secondary mt-2">
                                            <span class="fw-semibold">{{ trans('ronove::admin.audit.details') }}:</span>
                                            {{ $category === \Azuriom\Plugin\Ronove\Support\TranslationAuditIssue::INVALID_FALLBACKS ? trans('ronove::admin.audit.fallback_reasons.'.$issue->details) : $issue->details }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="card-footer ronove-audit-category-footer">
                        <span class="small text-body-secondary">
                            {{ $categoryIssues->count() > 50
                                ? trans('ronove::admin.audit.showing', ['shown' => 50, 'total' => $categoryIssues->count()])
                                : trans_choice('ronove::admin.audit.findings_count', $categoryIssues->count(), ['count' => $categoryIssues->count()]) }}
                        </span>
                        @if($cleanable)
                            <form method="POST" action="{{ route('ronove.admin.audit.cleanup', $category) }}" onsubmit="return confirm(@js(trans('ronove::admin.audit.confirm', ['category' => trans('ronove::admin.audit.categories.'.$category.'.title')])))">
                                @csrf
                                <input type="hidden" name="confirm" value="1">
                                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ trans('ronove::admin.audit.cleanup') }}</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endforeach
        @endif
    </div>
@endsection
