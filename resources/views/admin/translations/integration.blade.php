@extends('admin.layouts.admin')

@section('title', $integration->label())

@section('content')
    <div class="mb-4">
        <a class="text-decoration-none" href="{{ route('ronove.admin.translations.index') }}">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> {{ trans('ronove::admin.translations.back_to_center') }}
        </a>
        <div class="d-flex align-items-center gap-3 mt-2">
            <span class="fs-2 text-primary" aria-hidden="true"><i class="{{ $integration->icon }}"></i></span>
            <div>
                <h1 class="mb-1">{{ $integration->label() }}</h1>
                <p class="text-body-secondary mb-0">{{ trans('ronove::admin.translations.integration_description') }}</p>
            </div>
        </div>
    </div>

    @if($providers->count() > 1)
        <ul class="nav nav-tabs mb-4">
            @foreach($providers as $type => $registeredProvider)
                <li class="nav-item">
                    <a class="nav-link @if($type === $provider->type()) active @endif" href="{{ route('ronove.admin.translations.integration', ['integration' => $integration->id, 'type' => $type]) }}" @if($type === $provider->type()) aria-current="page" @endif>
                        {{ $registeredProvider->label() }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="h4 mb-3">{{ $provider->label() }}</h2>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('ronove::admin.translations.resource') }}</th>
                        <th>{{ trans('ronove::admin.translations.coverage') }}</th>
                        <th class="text-end">{{ trans('messages.actions.edit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resources as $resourceModel)
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
                                        <span class="badge {{ $storedTranslation?->isPublished() ? 'text-bg-success' : ($storedTranslation ? 'text-bg-warning' : 'text-bg-secondary') }}">
                                            {{ $locale->code }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="{{ route('ronove.admin.translations.edit', ['type' => $provider->type(), 'key' => $provider->key($resourceModel)]) }}">
                                    <i class="bi bi-translate" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ trans('messages.actions.edit') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-body-secondary py-5">{{ trans('ronove::admin.translations.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $resources->links() }}</div>
@endsection
