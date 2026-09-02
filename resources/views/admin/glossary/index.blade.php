@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.glossary.title'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    <a class="ronove-admin-back" href="{{ route('ronove.admin.translations.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ trans('ronove::admin.translations.back_to_center') }}</a>
    @include('ronove::admin._header', [
        'title' => trans('ronove::admin.glossary.title'),
        'description' => trans('ronove::admin.glossary.description'),
        'icon' => 'bi-journal-text',
        'actions' => $selectedLocale ? [[
            'url' => route('ronove.admin.glossary.create', ['scope' => $selectedScope, 'locale' => $selectedLocale->code]),
            'label' => trans('ronove::admin.glossary.add_action'),
            'icon' => 'bi-plus-lg',
            'class' => 'btn-primary',
        ]] : [],
    ])

    <form class="ronove-admin-toolbar mb-4" action="{{ route('ronove.admin.glossary.index') }}" method="GET">
        <div class="ronove-admin-toolbar-title"><i class="bi bi-funnel" aria-hidden="true"></i>{{ trans('ronove::admin.translations.filters.apply') }}</div>
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-4">
                <label class="form-label" for="glossaryScope">{{ trans('ronove::admin.glossary.scope') }}</label>
                <select class="form-select" id="glossaryScope" name="scope">
                    @foreach($scopes as $scope => $label)<option value="{{ $scope }}" @selected($selectedScope === $scope)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label" for="glossaryLocale">{{ trans('ronove::admin.glossary.locale') }}</label>
                <select class="form-select" id="glossaryLocale" name="locale">
                    @foreach($locales as $locale)<option value="{{ $locale->code }}" @selected($selectedLocale?->is($locale))>{{ \Azuriom\Plugin\Ronove\Support\CountryFlag::emoji($locale->flag_code) ?? '🌐' }} {{ $locale->native_name }}</option>@endforeach
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
        <div class="card ronove-admin-card">
            <div class="card-header ronove-glossary-table-header">
                <div><span class="ronove-admin-eyebrow">{{ trans('ronove::admin.glossary.title') }}</span><h2 class="h5 mb-0">{{ trans('ronove::admin.glossary.terms') }}</h2></div>
                <span class="ronove-glossary-count">{{ $terms->total() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 ronove-admin-table">
                    <thead><tr><th>{{ trans('ronove::admin.glossary.source_text') }}</th><th>{{ trans('ronove::admin.glossary.translated_text') }}</th><th>{{ trans('ronove::admin.glossary.context') }}</th><th class="text-end">{{ trans('ronove::admin.glossary.actions') }}</th></tr></thead>
                    <tbody>
                    @forelse($terms as $term)
                        <tr>
                            <td><strong>{{ $term->source_text }}</strong></td>
                            <td>{{ $term->translated_text }}</td>
                            <td class="ronove-glossary-context">{{ $term->context ?: trans('ronove::admin.glossary.no_context') }}</td>
                            <td class="text-end">
                                <div class="ronove-admin-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('ronove.admin.glossary.edit', $term) }}" title="{{ trans('messages.actions.edit') }}"><i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">{{ trans('messages.actions.edit') }}</span></a>
                                    <button class="btn btn-sm btn-outline-danger" type="submit" form="deleteGlossaryTerm{{ $term->id }}" title="{{ trans('messages.actions.delete') }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ trans('messages.actions.delete') }}</span></button>
                                </div>
                                <form id="deleteGlossaryTerm{{ $term->id }}" action="{{ route('ronove.admin.glossary.destroy', $term) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="ronove-admin-empty"><span class="ronove-admin-empty-icon" aria-hidden="true"><i class="bi bi-journal-x"></i></span><strong>{{ trans('ronove::admin.glossary.empty') }}</strong><a class="btn btn-primary btn-sm mt-2" href="{{ route('ronove.admin.glossary.create', ['scope' => $selectedScope, 'locale' => $selectedLocale->code]) }}"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ trans('ronove::admin.glossary.add_action') }}</a></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $terms->links() }}</div>
    @endif
    </div>
@endsection
