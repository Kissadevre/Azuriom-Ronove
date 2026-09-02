@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.translations.edit', ['resource' => $provider->title($resourceModel)]))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    <a class="ronove-admin-back" href="{{ route('ronove.admin.translations.integration', array_filter(['integration' => $integration->id, 'type' => $provider->type(), 'locale' => $selectedLocale?->code])) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.translations.back') }}</a>
    @include('ronove::admin._header', ['title' => $provider->title($resourceModel), 'description' => $provider->type().':'.$provider->key($resourceModel), 'icon' => 'bi-pencil-square'])

    <div class="ronove-content-tabs mb-4">
    <ul class="nav nav-pills flex-nowrap" role="tablist">
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
                        @php($localeBadge = $reviewWorkflowEnabled
                            ? match ($localeTranslation->review_status) {
                                'pending' => 'text-bg-info',
                                'changes_requested' => 'text-bg-danger',
                                'approved' => 'text-bg-success',
                                default => 'text-bg-warning',
                            }
                            : ($localeTranslation->isPublished() ? 'text-bg-success' : 'text-bg-warning'))
                        <span class="badge rounded-pill {{ $localeBadge }} ms-1">
                            {{ trans($reviewWorkflowEnabled
                                ? 'ronove::admin.reviews.status.'.$localeTranslation->review_status
                                : 'ronove::admin.translations.status.'.$localeTranslation->status) }}
                        </span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
    </div>

    @if($showOriginal)
        <div class="alert alert-info">{{ trans('ronove::admin.translations.original_help') }}</div>
        <div class="card ronove-admin-card">
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
        @if($reviewWorkflowEnabled && $translation)
            @php($reviewColor = match ($translation->review_status) {
                'pending' => 'info',
                'changes_requested' => 'danger',
                'approved' => 'success',
                default => 'warning',
            })
            <div class="alert alert-{{ $reviewColor }} ronove-review-status">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <strong>{{ trans('ronove::admin.reviews.current_status') }}:</strong>
                    <span class="badge text-bg-{{ $reviewColor }}">{{ trans('ronove::admin.reviews.status.'.$translation->review_status) }}</span>
                </div>
                @if($translation->review_feedback)
                    <hr>
                    <p class="mb-1 fw-semibold">{{ trans('ronove::admin.reviews.feedback') }}</p>
                    <p class="mb-0" style="white-space: pre-wrap">{{ $translation->review_feedback }}</p>
                    @if($translation->reviewer || $translation->reviewed_at)
                        <small class="d-block mt-2">
                            {{ trans('ronove::admin.reviews.reviewed_by', [
                                'user' => $translation->reviewer?->name ?? trans('ronove::admin.revisions.unknown_user'),
                                'date' => $translation->reviewed_at?->format('Y-m-d H:i') ?? '—',
                            ]) }}
                        </small>
                    @endif
                @endif
            </div>
        @endif

        <details class="card ronove-admin-card ronove-editor-disclosure mb-4" @if($glossaryTerms->isNotEmpty()) open @endif>
            <summary class="card-header">
                <div>
                    <h2 class="h5 mb-1">{{ trans('ronove::admin.glossary.suggestions') }}</h2>
                    <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.glossary.suggestions_help') }}</p>
                </div>
                <span class="ronove-disclosure-meta"><span class="badge rounded-pill text-bg-secondary">{{ $glossaryTerms->count() }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></span>
            </summary>
            <div class="card-body">
                <div class="d-flex justify-content-end mb-3">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('ronove.admin.glossary.index', ['scope' => $integration->id, 'locale' => $selectedLocale->code]) }}">
                        <i class="bi bi-journal-text me-1" aria-hidden="true"></i> {{ trans('ronove::admin.glossary.manage') }}
                    </a>
                </div>
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
        </details>

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

            @error('values')
                <div class="alert alert-danger" role="alert"><strong>{{ $message }}</strong></div>
            @enderror

            @if($previewGenerated)
                <div class="alert alert-info">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>
                    {{ trans('ronove::admin.translations.preview_generated') }}
                </div>
            @endif

            <div class="card ronove-admin-card mb-4">
                <div class="card-header ronove-editor-card-header">
                    <div>
                        <span class="ronove-admin-eyebrow">{{ $selectedLocale->native_name }}</span>
                        <h2 class="h5 mb-0">{{ $provider->label() }}</h2>
                    </div>
                    @if($translation)
                        <span class="badge rounded-pill text-bg-{{ $translation->isPublished() ? 'success' : 'warning' }}">{{ trans('ronove::admin.translations.status.'.$translation->status) }}</span>
                    @endif
                </div>
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

            <details class="card ronove-admin-card ronove-editor-disclosure mb-4" @if($previewGenerated) open @endif>
                <summary class="card-header">
                    <div>
                        <h2 class="h5 mb-1">{{ trans('ronove::admin.translations.preview_title') }}</h2>
                        <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.translations.preview_description') }}</p>
                    </div>
                    <span class="ronove-disclosure-meta"><i class="bi bi-chevron-down" aria-hidden="true"></i></span>
                </summary>
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
            </details>

            <div class="ronove-editor-actions">
                <div class="ronove-editor-actions-primary">
                @if($reviewWorkflowEnabled)
                    <div class="btn-group">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-save me-1" aria-hidden="true"></i>{{ trans('ronove::admin.translations.save_menu') }}
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" type="submit" name="workflow_action" value="save"><i class="bi bi-file-earmark me-2" aria-hidden="true"></i>{{ trans('ronove::admin.translations.save_draft') }}</button></li>
                            @if($canReview && $canPublish)
                                <li><button class="dropdown-item" type="submit" name="workflow_action" value="publish"><i class="bi bi-cloud-arrow-up me-2" aria-hidden="true"></i>{{ trans('ronove::admin.translations.save_publish') }}</button></li>
                            @endif
                        </ul>
                    </div>
                    <button class="btn btn-success" type="submit" name="workflow_action" value="submit">
                        <i class="bi bi-send me-1" aria-hidden="true"></i> {{ trans('ronove::admin.reviews.submit') }}
                    </button>
                @else
                    <div class="btn-group">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-save me-1" aria-hidden="true"></i>{{ trans('ronove::admin.translations.save_menu') }}
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" type="submit" name="status" value="draft"><i class="bi bi-file-earmark me-2" aria-hidden="true"></i>{{ trans('ronove::admin.translations.save_draft') }}</button></li>
                            @if($canPublish)
                                <li><button class="dropdown-item" type="submit" name="status" value="published"><i class="bi bi-cloud-arrow-up me-2" aria-hidden="true"></i>{{ trans('ronove::admin.translations.save_publish') }}</button></li>
                            @endif
                        </ul>
                    </div>
                @endif
                <button class="btn btn-outline-primary" type="submit" formaction="{{ route('ronove.admin.translations.preview', ['type' => $provider->type(), 'key' => $provider->key($resourceModel)]) }}">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i> {{ trans('ronove::admin.translations.preview_action') }}
                </button>
                </div>

                @if($translation)
                    <button class="btn btn-outline-danger ms-lg-auto" type="submit" form="deleteTranslationForm">
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

        @if($reviewWorkflowEnabled && $translation?->review_status === \Azuriom\Plugin\Ronove\Models\Translation::REVIEW_PENDING)
            <div class="card ronove-admin-card mt-4 border-info">
                <div class="card-header">
                    <h2 class="h5 mb-1">{{ trans('ronove::admin.reviews.panel_title') }}</h2>
                    <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.reviews.panel_description') }}</p>
                </div>
                <div class="card-body">
                    @if($canReview)
                        <form action="{{ route('ronove.admin.translations.reviews.changes', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale]) }}" method="POST">
                            @csrf
                            <label class="form-label" for="reviewFeedback">{{ trans('ronove::admin.reviews.feedback') }}</label>
                            <textarea class="form-control @error('feedback') is-invalid @enderror" id="reviewFeedback" name="feedback" rows="4" maxlength="5000" placeholder="{{ trans('ronove::admin.reviews.feedback_placeholder') }}">{{ old('feedback') }}</textarea>
                            @error('feedback')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                @if($canPublish)
                                    <button class="btn btn-success" type="submit" formaction="{{ route('ronove.admin.translations.reviews.approve', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale]) }}">
                                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ trans('ronove::admin.reviews.approve') }}
                                    </button>
                                @endif
                                <button class="btn btn-outline-danger" type="submit">
                                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ trans('ronove::admin.reviews.request_changes') }}
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-info mb-0">{{ trans('ronove::admin.reviews.waiting') }}</div>
                    @endif
                </div>
            </div>
        @endif

        @if($translation && $revisions->isNotEmpty())
            <details class="card ronove-admin-card ronove-editor-disclosure mt-4">
                <summary class="card-header">
                    <div>
                        <h2 class="h5 mb-1">{{ trans('ronove::admin.revisions.title') }}</h2>
                        <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.revisions.description') }}</p>
                    </div>
                    <span class="ronove-disclosure-meta"><span class="badge rounded-pill text-bg-secondary">{{ $revisions->total() }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></span>
                </summary>
                <div class="list-group list-group-flush">
                    @foreach($revisions as $revision)
                        <div class="list-group-item py-3">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <strong>#{{ $revision->id }} · {{ trans('ronove::admin.revisions.actions.'.$revision->action) }}</strong>
                                        <span class="badge text-bg-secondary">{{ trans('ronove::admin.translations.status.'.$revision->status) }}</span>
                                        <span class="badge text-bg-light border">{{ trans('ronove::admin.reviews.status.'.$revision->review_status) }}</span>
                                    </div>
                                    <small class="text-body-secondary">
                                        {{ trans('ronove::admin.revisions.metadata', [
                                            'user' => $revision->user?->name ?? trans('ronove::admin.revisions.unknown_user'),
                                            'date' => $revision->created_at->format('Y-m-d H:i'),
                                        ]) }}
                                    </small>
                                    @if($revision->action === \Azuriom\Plugin\Ronove\Models\TranslationRevision::RESTORED && str_starts_with($revision->feedback ?? '', 'revision:'))
                                        <p class="mt-2 mb-0 text-body-secondary">
                                            {{ trans('ronove::admin.revisions.restored_from', ['revision' => substr($revision->feedback, strlen('revision:'))]) }}
                                        </p>
                                    @elseif($revision->feedback)
                                        <p class="mt-2 mb-0" style="white-space: pre-wrap">{{ $revision->feedback }}</p>
                                    @endif
                                </div>
                                <form action="{{ route('ronove.admin.translations.revisions.restore', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale, 'revision' => $revision]) }}" method="POST" onsubmit="return confirm(@js(trans('ronove::admin.revisions.restore_confirm', ['revision' => $revision->id])))">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit">
                                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ trans('ronove::admin.revisions.restore') }}
                                    </button>
                                </form>
                            </div>
                            <details class="mt-3">
                                <summary class="text-primary">{{ trans('ronove::admin.revisions.view_values') }}</summary>
                                <div class="row g-3 mt-1">
                                    @foreach($provider->fields() as $field => $definition)
                                        <div class="col-12">
                                            <div class="small fw-semibold text-body-secondary mb-1">{{ $definition->label }}</div>
                                            @include('ronove::admin.translations._preview-value', ['value' => $revision->values[$field] ?? null])
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
                @if($revisions->hasPages())
                    <div class="card-footer">{{ $revisions->links() }}</div>
                @endif
            </details>
        @endif

        <details class="card ronove-admin-card ronove-editor-disclosure mt-4" @if($translationNote || $errors->has('note')) open @endif>
            <summary class="card-header">
                <div>
                    <h2 class="h5 mb-1">{{ trans('ronove::admin.translations.note_title') }}</h2>
                    <p class="text-body-secondary small mb-0">{{ trans('ronove::admin.translations.note_description') }}</p>
                </div>
                <span class="ronove-disclosure-meta">
                    @if($translationNote)<i class="bi bi-check-circle text-success" aria-hidden="true"></i>@endif
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </span>
            </summary>
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
        </details>

        @if($translationNote)
            <form id="deleteTranslationNoteForm" action="{{ route('ronove.admin.translations.notes.destroy', ['type' => $provider->type(), 'key' => $provider->key($resourceModel), 'locale' => $selectedLocale]) }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @else
        <div class="alert alert-info">{{ trans('ronove::admin.translations.no_languages') }}</div>
    @endif
    </div>
@endsection
