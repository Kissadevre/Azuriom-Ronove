@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.translations.title'))

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="mb-1">{{ trans('ronove::admin.translations.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ trans('ronove::admin.translations.description') }}</p>
        </div>
        <a class="btn btn-outline-primary align-self-start" href="{{ route('ronove.admin.glossary.index') }}">
            <i class="bi bi-journal-text me-1" aria-hidden="true"></i> {{ trans('ronove::admin.glossary.title') }}
        </a>
    </div>

    <div class="row g-4">
        @forelse($integrationGroups as $group)
            @php($integration = $group['integration'])
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="fs-2 text-primary" aria-hidden="true"><i class="{{ $integration->icon }}"></i></span>
                            <div>
                                <h2 class="h5 mb-1">{{ $integration->label() }}</h2>
                                <span class="text-body-secondary">
                                    {{ trans_choice('ronove::admin.translations.content_types', $group['providers']->count(), ['count' => $group['providers']->count()]) }}
                                </span>
                            </div>
                        </div>

                        <ul class="list-unstyled text-body-secondary mb-4">
                            @foreach($group['providers'] as $provider)
                                <li><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ $provider->label() }}</li>
                            @endforeach
                        </ul>

                        <a class="btn btn-primary mt-auto" href="{{ route('ronove.admin.translations.integration', $integration->id) }}">
                            <i class="bi bi-translate me-1" aria-hidden="true"></i>
                            {{ trans('ronove::admin.translations.manage_integration', ['integration' => $integration->label()]) }}
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info mb-0">{{ trans('ronove::admin.translations.no_integrations') }}</div>
            </div>
        @endforelse
    </div>
@endsection
