@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.audit.title'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    @can('ronove.translations')<a class="ronove-admin-back" href="{{ route('ronove.admin.translations.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.audit.back') }}</a>@endcan
    @include('ronove::admin._header', ['title' => trans('ronove::admin.audit.title'), 'description' => trans('ronove::admin.audit.description'), 'icon' => 'bi-shield-check', 'actions' => [['label' => trans('messages.actions.refresh'), 'url' => route('ronove.admin.audit.index'), 'icon' => 'bi-arrow-clockwise']]])

    <div class="alert alert-info" role="status">
        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
        {{ trans('ronove::admin.audit.scan_notice') }}
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <span class="text-body-secondary">{{ trans('ronove::admin.audit.total') }}</span>
                    <div class="display-6 mt-1">{{ $report->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-warning">
                <div class="card-body">
                    <span class="text-body-secondary">{{ trans('ronove::admin.audit.cleanable') }}</span>
                    <div class="display-6 mt-1">{{ $report->cleanableCount() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-info">
                <div class="card-body">
                    <span class="text-body-secondary">{{ trans('ronove::admin.audit.informational') }}</span>
                    <div class="display-6 mt-1">{{ $report->informationalCount() }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($report->isHealthy())
        <div class="alert alert-success">
            <h2 class="h5 alert-heading"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ trans('ronove::admin.audit.healthy') }}</h2>
            <p class="mb-0">{{ trans('ronove::admin.audit.healthy_help') }}</p>
        </div>
    @else
        @if($report->cleanableCount() > 0)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                {{ trans('ronove::admin.audit.cleanup_notice') }}
            </div>
        @endif

        @foreach($categories as $category)
            @php($categoryIssues = $report->issuesFor($category))
            @continue($categoryIssues->isEmpty())

            <section class="card ronove-admin-card mb-4">
                <div class="card-header d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="h5 mb-0">{{ trans('ronove::admin.audit.categories.'.$category.'.title') }}</h2>
                            <span class="badge {{ $categoryIssues->first()->isCleanable() ? 'text-bg-warning' : 'text-bg-info' }}">
                                {{ $categoryIssues->count() }}
                            </span>
                        </div>
                        <p class="text-body-secondary mb-0">{{ trans('ronove::admin.audit.categories.'.$category.'.description') }}</p>
                    </div>

                    @if($categoryIssues->first()->isCleanable())
                        <form method="POST" action="{{ route('ronove.admin.audit.cleanup', $category) }}" onsubmit="return confirm(@js(trans('ronove::admin.audit.confirm', ['category' => trans('ronove::admin.audit.categories.'.$category.'.title')])))">
                            @csrf
                            <input type="hidden" name="confirm" value="1">
                            <button class="btn btn-outline-danger text-nowrap" type="submit">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ trans('ronove::admin.audit.cleanup') }}
                            </button>
                        </form>
                    @else
                        <span class="badge text-bg-info">{{ trans('ronove::admin.audit.manual_only') }}</span>
                    @endif
                </div>

                <div class="list-group list-group-flush">
                    @foreach($categoryIssues->take(50) as $issue)
                        <div class="list-group-item py-3">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge text-bg-secondary">{{ trans('ronove::admin.audit.record_types.'.$issue->recordType) }}</span>
                                <strong class="text-break">{{ $issue->reference }}</strong>
                                @if($issue->locale)
                                    <span class="badge text-bg-light border">{{ $issue->locale }}</span>
                                @endif
                            </div>
                            @if($issue->details)
                                <div class="small text-body-secondary mt-2">
                                    <span class="fw-semibold">{{ trans('ronove::admin.audit.details') }}:</span>
                                    {{ $category === \Azuriom\Plugin\Ronove\Support\TranslationAuditIssue::INVALID_FALLBACKS
                                        ? trans('ronove::admin.audit.fallback_reasons.'.$issue->details)
                                        : $issue->details }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($categoryIssues->count() > 50)
                    <div class="card-footer text-body-secondary small">
                        {{ trans('ronove::admin.audit.showing', ['shown' => 50, 'total' => $categoryIssues->count()]) }}
                    </div>
                @endif
            </section>
        @endforeach
    @endif
    </div>
@endsection
