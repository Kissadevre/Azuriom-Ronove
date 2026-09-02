@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.translations.title'))

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="mb-1">{{ trans('ronove::admin.translations.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ trans('ronove::admin.translations.description') }}</p>
        </div>

        @if($providers->count() > 1)
            <form action="{{ route('ronove.admin.translations.index') }}" method="GET">
                <label class="visually-hidden" for="resourceTypeSelect">{{ trans('ronove::admin.translations.resource_type') }}</label>
                <select class="form-select" id="resourceTypeSelect" name="type" onchange="this.form.submit()">
                    @foreach($providers as $type => $registeredProvider)
                        <option value="{{ $type }}" @selected($type === $provider->type())>{{ $registeredProvider->label() }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

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
