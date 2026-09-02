@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.translations.edit', ['resource' => $provider->title($resourceModel)]))

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <a class="text-decoration-none" href="{{ route('ronove.admin.translations.integration', array_filter(['integration' => $integration->id, 'type' => $provider->type(), 'locale' => $selectedLocale?->code])) }}">
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
        <div class="card mb-4">
            <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-1">{{ trans('ronove::admin.glossary.suggestions') }}</h2>
                    <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.glossary.suggestions_help') }}</p>
                </div>
                <a class="btn btn-sm btn-outline-primary align-self-start" href="{{ route('ronove.admin.glossary.index', ['scope' => $integration->id, 'locale' => $selectedLocale->code]) }}">
                    <i class="bi bi-journal-text me-1" aria-hidden="true"></i> {{ trans('ronove::admin.glossary.manage') }}
                </a>
            </div>
            <div class="card-body">
                @forelse($glossaryTerms as $term)
                    <div class="@if(! $loop->last) border-bottom pb-3 mb-3 @endif">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <strong>{{ $term->source_text }}</strong>
                            <i class="bi bi-arrow-right text-body-secondary" aria-hidden="true"></i>
                            <span>{{ $term->translated_text }}</span>
                            <span class="badge text-bg-secondary">
                                {{ $term->scope === \Azuriom\Plugin\Ronove\Models\GlossaryTerm::GLOBAL_SCOPE
                                    ? trans('ronove::admin.glossary.global_scope')
                                    : $integration->label() }}
                            </span>
                        </div>
                        @if($term->context)
                            <small class="text-body-secondary">{{ $term->context }}</small>
                        @endif
                    </div>
                @empty
                    <span class="text-body-secondary">{{ trans('ronove::admin.glossary.no_suggestions') }}</span>
                @endforelse
            </div>
        </div>

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

            @if($previewGenerated)
                <div class="alert alert-info">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>
                    {{ trans('ronove::admin.translations.preview_generated') }}
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    @foreach($provider->fields() as $field => $definition)
                        @php($fieldValue = old('values.'.$field, $editorValues[$field] ?? ''))
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

            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-1">{{ trans('ronove::admin.translations.preview_title') }}</h2>
                    <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.translations.preview_description') }}</p>
                </div>
                <div class="card-body">
                    @foreach($provider->fields() as $field => $definition)
                        @php($fieldPreview = $previewFields[$field])
                        @php($sourceLocale = $fieldPreview->sourceLocale === null ? null : $locales->firstWhere('code', $fieldPreview->sourceLocale))
                        <section class="@if(! $loop->last) border-bottom pb-4 mb-4 @endif">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <h3 class="h6 mb-0">{{ $definition->label }}</h3>
                                <span class="badge text-bg-secondary">
                                    @if($fieldPreview->source === \Azuriom\Plugin\Ronove\Support\TranslationPreviewField::SELECTED)
                                        {{ trans('ronove::admin.translations.preview_source_selected', ['locale' => $selectedLocale->native_name]) }}
                                    @elseif($fieldPreview->source === \Azuriom\Plugin\Ronove\Support\TranslationPreviewField::FALLBACK)
                                        {{ trans('ronove::admin.translations.preview_source_fallback', ['locale' => $sourceLocale?->native_name ?? $fieldPreview->sourceLocale]) }}
                                    @elseif($fieldPreview->source === \Azuriom\Plugin\Ronove\Support\TranslationPreviewField::GLOBAL)
                                        {{ trans('ronove::admin.translations.preview_source_global', ['locale' => $sourceLocale?->native_name ?? $fieldPreview->sourceLocale]) }}
                                    @else
                                        {{ trans('ronove::admin.translations.preview_source_original') }}
                                    @endif
                                </span>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="small fw-semibold text-body-secondary mb-1">{{ trans('ronove::admin.translations.preview_original') }}</div>
                                    @include('ronove::admin.translations._preview-value', ['value' => $fieldPreview->original])
                                </div>
                                <div class="col-lg-6">
                                    <div class="small fw-semibold text-body-secondary mb-1">{{ trans('ronove::admin.translations.preview_result') }}</div>
                                    @include('ronove::admin.translations._preview-value', ['value' => $fieldPreview->value])
                                </div>
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <select class="form-select w-auto" name="status" aria-label="{{ trans('ronove::admin.translations.status_label') }}">
                    <option value="draft" @selected(old('status', $formStatus) === 'draft')>{{ trans('ronove::admin.translations.status.draft') }}</option>
                    @if($canPublish)
                        <option value="published" @selected(old('status', $formStatus) === 'published')>{{ trans('ronove::admin.translations.status.published') }}</option>
                    @endif
                </select>
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-save me-1" aria-hidden="true"></i> {{ trans('messages.actions.save') }}
                </button>
                <button class="btn btn-outline-primary" type="submit" formaction="{{ route('ronove.admin.translations.preview', ['type' => $provider->type(), 'key' => $provider->key($resourceModel)]) }}">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i> {{ trans('ronove::admin.translations.preview_action') }}
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

        <div class="card mt-4">
            <div class="card-header">
                <h2 class="h5 mb-1">{{ trans('ronove::admin.translations.note_title') }}</h2>
                <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.translations.note_description') }}</p>
            </div>
            <div class="card-body">
                <form action="{{ route('ronove.admin.translations.notes.update', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale]) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <label class="form-label" for="translationNote">{{ trans('ronove::admin.translations.note_title') }}</label>
                    <textarea class="form-control @error('note') is-invalid @enderror" id="translationNote" name="note" rows="4" maxlength="5000" placeholder="{{ trans('ronove::admin.translations.note_placeholder') }}" required>{{ old('note', $translationNote?->note) }}</textarea>
                    @error('note')
                        <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                    @enderror
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1" aria-hidden="true"></i> {{ trans('messages.actions.save') }}
                        </button>
                        @if($translationNote)
                            <button class="btn btn-outline-danger" type="submit" form="deleteTranslationNoteForm">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i> {{ trans('messages.actions.delete') }}
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if($translationNote)
            <form id="deleteTranslationNoteForm" action="{{ route('ronove.admin.translations.notes.destroy', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale]) }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @else
        <div class="alert alert-info">{{ trans('ronove::admin.translations.no_languages') }}</div>
    @endif
@endsection
