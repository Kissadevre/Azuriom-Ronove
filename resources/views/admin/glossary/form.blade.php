@extends('admin.layouts.admin')

@section('title', trans($term ? 'ronove::admin.glossary.edit_title' : 'ronove::admin.glossary.add'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    <a class="ronove-admin-back" href="{{ route('ronove.admin.glossary.index', array_filter(['scope' => old('scope', $selectedScope), 'locale' => old('locale', $selectedLocale?->code)])) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.glossary.back') }}</a>
    @include('ronove::admin._header', [
        'title' => trans($term ? 'ronove::admin.glossary.edit_title' : 'ronove::admin.glossary.add'),
        'description' => trans($term ? 'ronove::admin.glossary.edit_description' : 'ronove::admin.glossary.add_description'),
        'icon' => $term ? 'bi-pencil-square' : 'bi-journal-plus',
    ])

    @if($locales->isEmpty())
        <div class="alert alert-info">{{ trans('ronove::admin.glossary.no_languages') }}</div>
    @else
        <form class="card ronove-admin-card" action="{{ $term ? route('ronove.admin.glossary.update', $term) : route('ronove.admin.glossary.store') }}" method="POST">
            @csrf
            @if($term) @method('PUT') @endif
            <div class="card-body ronove-glossary-form-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="glossaryScope">{{ trans('ronove::admin.glossary.scope') }}</label>
                        <select class="form-select @error('scope') is-invalid @enderror" id="glossaryScope" name="scope" required>
                            @foreach($scopes as $scope => $label)<option value="{{ $scope }}" @selected(old('scope', $selectedScope) === $scope)>{{ $label }}</option>@endforeach
                        </select>
                        @error('scope')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="glossaryLocale">{{ trans('ronove::admin.glossary.locale') }}</label>
                        <select class="form-select @error('locale') is-invalid @enderror" id="glossaryLocale" name="locale" required>
                            @foreach($locales as $locale)<option value="{{ $locale->code }}" @selected(old('locale', $selectedLocale?->code) === $locale->code)>{{ \Azuriom\Plugin\Ronove\Support\CountryFlag::emoji($locale->flag_code) ?? '🌐' }} {{ $locale->native_name }}</option>@endforeach
                        </select>
                        @error('locale')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="glossarySource">{{ trans('ronove::admin.glossary.source_text') }}</label>
                        <input class="form-control @error('source_text') is-invalid @enderror" id="glossarySource" name="source_text" value="{{ old('source_text', $term?->source_text) }}" maxlength="100" required autofocus>
                        @error('source_text')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="glossaryTranslation">{{ trans('ronove::admin.glossary.translated_text') }}</label>
                        <input class="form-control @error('translated_text') is-invalid @enderror" id="glossaryTranslation" name="translated_text" value="{{ old('translated_text', $term?->translated_text) }}" maxlength="191" required>
                        @error('translated_text')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="glossaryContext">{{ trans('ronove::admin.glossary.context') }}</label>
                        <textarea class="form-control @error('context') is-invalid @enderror" id="glossaryContext" name="context" rows="4" maxlength="2000">{{ old('context', $term?->context) }}</textarea>
                        @error('context')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                        <div class="form-text">{{ trans('ronove::admin.glossary.context_help') }}</div>
                    </div>
                </div>
            </div>
            <div class="card-footer ronove-glossary-card-footer gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('ronove.admin.glossary.index', ['scope' => old('scope', $selectedScope), 'locale' => old('locale', $selectedLocale?->code)]) }}">{{ trans('messages.actions.cancel') }}</a>
                <button class="btn btn-primary" type="submit"><i class="bi {{ $term ? 'bi-save' : 'bi-plus-lg' }} me-1" aria-hidden="true"></i>{{ trans($term ? 'messages.actions.save' : 'ronove::admin.glossary.add_action') }}</button>
            </div>
        </form>
    @endif
    </div>
@endsection
