@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.glossary.title'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    @php($editingTerm = old('editing_term'))

    <a class="ronove-admin-back" href="{{ route('ronove.admin.translations.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.translations.back_to_center') }}</a>
    @include('ronove::admin._header', ['title' => trans('ronove::admin.glossary.title'), 'description' => trans('ronove::admin.glossary.description'), 'icon' => 'bi-journal-text'])

    <form class="ronove-admin-toolbar mb-4" action="{{ route('ronove.admin.glossary.index') }}" method="GET">
        <div class="ronove-admin-toolbar-title">
            <i class="bi bi-funnel" aria-hidden="true"></i>
            {{ trans('ronove::admin.translations.filters.apply') }}
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-4">
                <label class="form-label" for="glossaryScope">{{ trans('ronove::admin.glossary.scope') }}</label>
                <select class="form-select" id="glossaryScope" name="scope">
                    @foreach($scopes as $scope => $label)
                        <option value="{{ $scope }}" @selected($selectedScope === $scope)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label" for="glossaryLocale">{{ trans('ronove::admin.glossary.locale') }}</label>
                <select class="form-select" id="glossaryLocale" name="locale">
                    @foreach($locales as $locale)
                        <option value="{{ $locale->code }}" @selected($selectedLocale?->is($locale))>{{ $locale->native_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8 col-lg-3">
                <label class="form-label" for="glossarySearch">{{ trans('ronove::admin.glossary.search') }}</label>
                <input class="form-control" id="glossarySearch" name="search" value="{{ $search }}" maxlength="100">
            </div>
            <div class="col-md-4 col-lg-3 ronove-admin-filter-actions">
                <button class="btn btn-primary" type="submit">{{ trans('ronove::admin.translations.filters.apply') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('ronove.admin.glossary.index') }}">{{ trans('ronove::admin.translations.filters.clear') }}</a>
            </div>
        </div>
    </form>

    @if($selectedLocale === null)
        <div class="alert alert-info">{{ trans('ronove::admin.glossary.no_languages') }}</div>
    @else
        <div class="card ronove-admin-card mb-4">
            <div class="card-header ronove-glossary-card-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="ronove-setting-icon text-primary bg-primary bg-opacity-10" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                    <h2 class="h5 mb-0">{{ trans('ronove::admin.glossary.add') }}</h2>
                </div>
                <span class="badge rounded-pill text-bg-light border">{{ $selectedLocale->native_name }}</span>
            </div>
            <form action="{{ route('ronove.admin.glossary.store') }}" method="POST">
                <div class="card-body ronove-glossary-form-body">
                    @csrf
                    <input type="hidden" name="scope" value="{{ $selectedScope }}">
                    <input type="hidden" name="locale" value="{{ $selectedLocale->code }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="newGlossarySource">{{ trans('ronove::admin.glossary.source_text') }}</label>
                            <input class="form-control @if($editingTerm === null) @error('source_text') is-invalid @enderror @endif" id="newGlossarySource" name="source_text" value="{{ $editingTerm === null ? old('source_text') : '' }}" maxlength="100" required>
                            @if($editingTerm === null)
                                @error('source_text')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newGlossaryTranslation">{{ trans('ronove::admin.glossary.translated_text') }}</label>
                            <input class="form-control @if($editingTerm === null) @error('translated_text') is-invalid @enderror @endif" id="newGlossaryTranslation" name="translated_text" value="{{ $editingTerm === null ? old('translated_text') : '' }}" maxlength="191" required>
                            @if($editingTerm === null)
                                @error('translated_text')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="newGlossaryContext">{{ trans('ronove::admin.glossary.context') }}</label>
                            <textarea class="form-control @if($editingTerm === null) @error('context') is-invalid @enderror @endif" id="newGlossaryContext" name="context" rows="2" maxlength="2000">{{ $editingTerm === null ? old('context') : '' }}</textarea>
                            @if($editingTerm === null)
                                @error('context')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            @endif
                            <div class="form-text">{{ trans('ronove::admin.glossary.context_help') }}</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer ronove-glossary-card-footer">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> {{ trans('ronove::admin.glossary.add_action') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="ronove-glossary-list-header">
            <div>
                <span class="ronove-admin-eyebrow">{{ trans('ronove::admin.glossary.title') }}</span>
                <h2 class="h4 mb-0">{{ trans('ronove::admin.glossary.terms') }}</h2>
            </div>
            <span class="ronove-glossary-count">{{ $terms->total() }}</span>
        </div>

        @forelse($terms as $term)
            @php($isEditingTerm = (string) $editingTerm === (string) $term->id)
            <form class="card ronove-admin-card mb-3" action="{{ route('ronove.admin.glossary.update', $term) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="scope" value="{{ $selectedScope }}">
                <input type="hidden" name="locale" value="{{ $selectedLocale->code }}">
                <div class="card-body ronove-glossary-form-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="glossarySource{{ $term->id }}">{{ trans('ronove::admin.glossary.source_text') }}</label>
                            <input class="form-control @if($isEditingTerm) @error('source_text') is-invalid @enderror @endif" id="glossarySource{{ $term->id }}" name="source_text" value="{{ $isEditingTerm ? old('source_text', $term->source_text) : $term->source_text }}" maxlength="100" required>
                            @if($isEditingTerm)
                                @error('source_text')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="glossaryTranslation{{ $term->id }}">{{ trans('ronove::admin.glossary.translated_text') }}</label>
                            <input class="form-control @if($isEditingTerm) @error('translated_text') is-invalid @enderror @endif" id="glossaryTranslation{{ $term->id }}" name="translated_text" value="{{ $isEditingTerm ? old('translated_text', $term->translated_text) : $term->translated_text }}" maxlength="191" required>
                            @if($isEditingTerm)
                                @error('translated_text')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="glossaryContext{{ $term->id }}">{{ trans('ronove::admin.glossary.context') }}</label>
                            <textarea class="form-control @if($isEditingTerm) @error('context') is-invalid @enderror @endif" id="glossaryContext{{ $term->id }}" name="context" rows="2" maxlength="2000">{{ $isEditingTerm ? old('context', $term->context) : $term->context }}</textarea>
                            @if($isEditingTerm)
                                @error('context')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-footer ronove-glossary-term-actions">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save me-1" aria-hidden="true"></i> {{ trans('messages.actions.save') }}
                    </button>
                    <button class="btn btn-outline-danger" type="submit" form="deleteGlossaryTerm{{ $term->id }}">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i> {{ trans('messages.actions.delete') }}
                    </button>
                </div>
            </form>
            <form id="deleteGlossaryTerm{{ $term->id }}" action="{{ route('ronove.admin.glossary.destroy', $term) }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @empty
            <div class="card ronove-admin-card">
                <div class="ronove-admin-empty">
                    <span class="ronove-admin-empty-icon" aria-hidden="true"><i class="bi bi-journal-x"></i></span>
                    <strong>{{ trans('ronove::admin.glossary.empty') }}</strong>
                </div>
            </div>
        @endforelse

        <div class="mt-3">{{ $terms->links() }}</div>
    @endif
    </div>
@endsection
