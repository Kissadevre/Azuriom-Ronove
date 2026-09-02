@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.translations.edit', ['resource' => $provider->title($resourceModel)]))

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <a class="text-decoration-none" href="{{ route('ronove.admin.translations.integration', ['integration' => $integration->id, 'type' => $provider->type()]) }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> {{ trans('ronove::admin.translations.back') }}
            </a>
            <h1 class="mt-2 mb-1">{{ $provider->title($resourceModel) }}</h1>
            <small class="text-body-secondary">{{ $provider->type() }}:{{ $provider->key($resourceModel) }}</small>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link @if($showOriginal) active @endif" href="{{ route('ronove.admin.translations.edit', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => 'original']) }}">
                {{ trans('ronove::admin.translations.original') }}
            </a>
        </li>
        @foreach($locales as $locale)
            @php($localeTranslation = $resourceRecord?->translations?->firstWhere('locale_id', $locale->id))
            <li class="nav-item" role="presentation">
                <a class="nav-link @if($selectedLocale?->is($locale)) active @endif" href="{{ route('ronove.admin.translations.edit', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $locale->code]) }}">
                    {{ $locale->native_name }}
                    @if($localeTranslation)
                        <span class="badge rounded-pill {{ $localeTranslation->isPublished() ? 'text-bg-success' : 'text-bg-warning' }} ms-1">
                            {{ trans('ronove::admin.translations.status.'.$localeTranslation->status) }}
                        </span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    @if($showOriginal)
        <div class="alert alert-info">{{ trans('ronove::admin.translations.original_help') }}</div>
        <div class="card">
            <div class="card-body">
                @foreach($provider->fields() as $field => $definition)
                    <div class="mb-3 @if($loop->last) mb-0 @endif">
                        <label class="form-label fw-semibold">{{ $definition->label }}</label>
                        @if($definition->type === \Azuriom\Plugin\Ronove\Support\TranslatableField::RICH_TEXT)
                            <div class="form-control bg-body-secondary" style="min-height: 8rem">{!! $provider->original($resourceModel, $field) !!}</div>
                        @else
                            <div class="form-control bg-body-secondary">{{ $provider->original($resourceModel, $field) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @elseif($selectedLocale)
        <form action="{{ route('ronove.admin.translations.update', ['type' => $provider->type(), 'key' => $provider->key($resourceModel)]) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="locale" value="{{ $selectedLocale->code }}">

            @if(collect($provider->fields())->contains(fn ($field) => $field->type === \Azuriom\Plugin\Ronove\Support\TranslatableField::RICH_TEXT))
                @include('admin.elements.editor')
            @endif

            @if($translation && $translation->source_hash !== $sourceHash)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                    {{ trans('ronove::admin.translations.source_changed') }}
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    @foreach($provider->fields() as $field => $definition)
                        @php($fieldValue = old('values.'.$field, $translation?->values[$field] ?? ''))
                        <div class="mb-3 @if($loop->last) mb-0 @endif">
                            <label class="form-label fw-semibold" for="translation{{ ucfirst($field) }}">{{ $definition->label }}</label>

                            @if($definition->type === \Azuriom\Plugin\Ronove\Support\TranslatableField::TEXT)
                                <input class="form-control @error('values.'.$field) is-invalid @enderror" id="translation{{ ucfirst($field) }}" name="values[{{ $field }}]" value="{{ $fieldValue }}" @if($definition->maxLength) maxlength="{{ $definition->maxLength }}" @endif>
                            @else
                                <textarea class="form-control @if($definition->type === \Azuriom\Plugin\Ronove\Support\TranslatableField::RICH_TEXT) html-editor @endif @error('values.'.$field) is-invalid @enderror" id="translation{{ ucfirst($field) }}" name="values[{{ $field }}]" rows="8">{{ $fieldValue }}</textarea>
                            @endif

                            @error('values.'.$field)
                                <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                            <div class="form-text">{{ trans('ronove::admin.translations.empty_fallback') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <select class="form-select w-auto" name="status" aria-label="{{ trans('ronove::admin.translations.status_label') }}">
                    <option value="draft" @selected(old('status', $translation?->status ?? 'draft') === 'draft')>{{ trans('ronove::admin.translations.status.draft') }}</option>
                    @if($canPublish)
                        <option value="published" @selected(old('status', $translation?->status) === 'published')>{{ trans('ronove::admin.translations.status.published') }}</option>
                    @endif
                </select>
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-save me-1" aria-hidden="true"></i> {{ trans('messages.actions.save') }}
                </button>

                @if($translation)
                    <button class="btn btn-danger" type="submit" form="deleteTranslationForm">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i> {{ trans('messages.actions.delete') }}
                    </button>
                @endif
            </div>
        </form>

        @if($translation)
            <form id="deleteTranslationForm" action="{{ route('ronove.admin.translations.destroy', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale]) }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @else
        <div class="alert alert-info">{{ trans('ronove::admin.translations.no_languages') }}</div>
    @endif
@endsection
