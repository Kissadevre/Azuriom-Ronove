@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.translations.title'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    @include('ronove::admin._header', [
        'title' => trans('ronove::admin.translations.title'),
        'description' => trans('ronove::admin.translations.description'),
        'icon' => 'bi-translate',
    ])

    <div class="row g-4">
        @forelse($integrationGroups as $group)
            @php($integration = $group['integration'])
            <div class="col-md-6 col-xl-4">
                <div class="card ronove-admin-card ronove-integration-card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="ronove-integration-icon text-primary" aria-hidden="true"><i class="{{ $integration->icon }}"></i></span>
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
                <div class="ronove-admin-empty"><span class="ronove-admin-empty-icon"><i class="bi bi-puzzle"></i></span><strong>{{ trans('ronove::admin.translations.no_integrations') }}</strong></div>
            </div>
        @endforelse
    </div>
    </div>
@endsection
