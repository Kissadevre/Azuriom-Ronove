@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.languages.title'))

@include('ronove::admin._assets')

@push('footer-scripts')
    <script>
        (() => {
            const search = document.getElementById('ronoveLocaleSearch');
            const options = Array.from(document.querySelectorAll('[data-ronove-locale]'));
            const selectedCount = document.getElementById('ronoveSelectedLocaleCount');
            const empty = document.getElementById('ronoveLocaleSearchEmpty');

            if (! search || options.length === 0) return;

            const refresh = () => {
                const query = search.value.trim().toLocaleLowerCase();
                let visible = 0;

                options.forEach((option) => {
                    const matches = option.dataset.ronoveLocale.includes(query);
                    option.classList.toggle('d-none', ! matches);
                    if (matches) visible++;
                });

                selectedCount.textContent = options.filter((option) => option.querySelector('input[type="checkbox"]').checked).length;
                empty.classList.toggle('d-none', visible !== 0);
            };

            const flagEmoji = (code) => /^[a-z]{2}$/i.test(code)
                ? [...code.toUpperCase()].map((letter) => String.fromCodePoint(127397 + letter.charCodeAt(0))).join('')
                : '🌐';

            search.addEventListener('input', refresh);
            options.forEach((option) => {
                option.querySelector('input[type="checkbox"]').addEventListener('change', refresh);
                const flagInput = option.querySelector('[data-ronove-flag-input]');
                const preview = option.querySelector('[data-ronove-flag-preview]');

                flagInput.addEventListener('input', () => {
                    flagInput.value = flagInput.value.replace(/[^a-z]/gi, '').slice(0, 2).toUpperCase();
                    preview.textContent = flagEmoji(flagInput.value);
                });
            });
            refresh();
        })();
    </script>
@endpush

@section('content')
    <div class="ronove-admin-shell">
    @include('ronove::admin._header', [
        'title' => trans('ronove::admin.languages.title'),
        'description' => trans('ronove::admin.languages.description'),
        'icon' => 'bi-globe2',
    ])

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="ronove-admin-stat"><div><span class="ronove-admin-stat-label">{{ trans('ronove::admin.languages.global_label') }}</span><strong class="ronove-admin-stat-value fs-5">{{ $globalLocaleName }}</strong></div><span class="ronove-admin-stat-icon text-primary bg-primary bg-opacity-10"><i class="bi bi-file-earmark-text"></i></span></div></div>
        <div class="col-md-4"><div class="ronove-admin-stat"><div><span class="ronove-admin-stat-label">{{ trans('ronove::admin.languages.selected') }}</span><strong class="ronove-admin-stat-value" id="ronoveSelectedLocaleCount">{{ $enabledLocales->count() }}</strong></div><span class="ronove-admin-stat-icon text-success bg-success bg-opacity-10"><i class="bi bi-check2-circle"></i></span></div></div>
        <div class="col-md-4"><div class="ronove-admin-stat"><div><span class="ronove-admin-stat-label">{{ trans('ronove::admin.languages.available') }}</span><strong class="ronove-admin-stat-value">{{ $availableLocales->count() }}</strong></div><span class="ronove-admin-stat-icon text-info bg-info bg-opacity-10"><i class="bi bi-globe-americas"></i></span></div></div>
    </div>

    <form action="{{ route('ronove.admin.languages.update') }}" method="POST">
        @csrf

        <div class="card ronove-admin-card mb-4">
            <div class="card-header ronove-language-card-header">
                <div><span class="ronove-admin-eyebrow">{{ trans('ronove::admin.languages.step_languages') }}</span><h2 class="h5 mb-1">{{ trans('ronove::admin.languages.available_title') }}</h2><p class="text-body-secondary small mb-0">{{ trans('ronove::admin.languages.available_description') }} {{ trans('ronove::admin.languages.flag_description') }}</p></div>
                <div class="ronove-language-search"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" id="ronoveLocaleSearch" type="search" placeholder="{{ trans('ronove::admin.languages.search') }}" aria-label="{{ trans('ronove::admin.languages.search') }}"></div>
            </div>
            <div class="card-body">
                @error('locales')
                    <div class="alert alert-danger" role="alert"><strong>{{ $message }}</strong></div>
                @enderror

                <div class="row g-3">
                    @foreach($availableLocales as $code => $name)
                        @php($normalizedCode = \Azuriom\Plugin\Ronove\Support\LocaleCode::normalize($code))
                        @php($enabled = old('locales') !== null
                            ? in_array($code, old('locales', []), true)
                            : ($configuredLocales->get($normalizedCode)?->is_enabled ?? false))
                        @php($flagCode = old('flags.'.$normalizedCode, $configuredLocales->get($normalizedCode)?->flag_code ?? \Azuriom\Plugin\Ronove\Support\CountryFlag::defaultForLocale($normalizedCode)))
                        <div class="col-md-6 col-xl-4" data-ronove-locale="{{ mb_strtolower($name.' '.$normalizedCode) }}">
                            <div class="ronove-locale-option border rounded p-3 d-flex align-items-center gap-3 h-100">
                                <label class="d-flex min-w-0 flex-grow-1 align-items-center gap-3" for="locale{{ $loop->index }}">
                                    <input class="form-check-input mt-0" id="locale{{ $loop->index }}" type="checkbox" name="locales[]" value="{{ $code }}" @checked($enabled)>
                                    <span class="flex-grow-1"><strong class="d-block">{{ $name }}</strong><small class="text-body-secondary">{{ $normalizedCode }}</small></span>
                                </label>
                                <div class="ronove-flag-field">
                                    <span class="ronove-locale-flag ronove-locale-flag-lg" data-ronove-flag-preview aria-hidden="true">{{ \Azuriom\Plugin\Ronove\Support\CountryFlag::emoji($flagCode) ?? '🌐' }}</span>
                                    <label class="visually-hidden" for="flag{{ $loop->index }}">{{ trans('ronove::admin.languages.flag_for', ['locale' => $name]) }}</label>
                                    <input class="form-control form-control-sm text-uppercase @error('flags.'.$normalizedCode) is-invalid @enderror" id="flag{{ $loop->index }}" name="flags[{{ $normalizedCode }}]" value="{{ $flagCode }}" maxlength="2" inputmode="text" autocomplete="off" placeholder="ES" data-ronove-flag-input>
                                    @error('flags.'.$normalizedCode)<div class="invalid-feedback d-block"><strong>{{ $message }}</strong></div>@enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="ronove-admin-empty d-none" id="ronoveLocaleSearchEmpty"><span class="ronove-admin-empty-icon"><i class="bi bi-search"></i></span><strong>{{ trans('ronove::admin.languages.no_search_results') }}</strong></div>
            </div>
            <div class="card-footer ronove-language-card-footer"><span class="small text-body-secondary">{{ trans('ronove::admin.languages.save_selection_help') }}</span><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ trans('messages.actions.save') }}</button></div>
        </div>
    </form>

    @if($enabledLocales->isNotEmpty())
        <form action="{{ route('ronove.admin.languages.fallbacks.update') }}" method="POST">
            @csrf

            <div class="card ronove-admin-card mb-4">
                <div class="card-header"><span class="ronove-admin-eyebrow">{{ trans('ronove::admin.languages.step_fallbacks') }}</span><h2 class="h5 mb-1">{{ trans('ronove::admin.languages.fallbacks_title') }}</h2><p class="text-body-secondary small mb-0">{{ trans('ronove::admin.languages.fallbacks_description') }}</p></div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($enabledLocales as $locale)
                            <div class="col-md-6">
                                <div class="ronove-fallback-option h-100">
                                <label class="form-label" for="fallback{{ $locale->id }}">
                                    {{ trans('ronove::admin.languages.fallback_for', ['locale' => $locale->native_name]) }}
                                </label>
                                <select class="form-select @error('fallbacks.'.$locale->code) is-invalid @enderror" id="fallback{{ $locale->id }}" name="fallbacks[{{ $locale->code }}]">
                                    <option value="">
                                        {{ trans($locale->code === $globalLocale
                                            ? 'ronove::admin.languages.fallback_original'
                                            : 'ronove::admin.languages.fallback_global') }}
                                    </option>
                                    @foreach($enabledLocales as $candidate)
                                        @continue($candidate->is($locale))
                                        <option value="{{ $candidate->code }}" @selected(old('fallbacks.'.$locale->code, $locale->fallback?->code) === $candidate->code)>
                                            {{ $candidate->native_name }} ({{ $candidate->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('fallbacks.'.$locale->code)
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                                <div class="form-text">{{ trans('ronove::admin.languages.fallback_help') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer ronove-language-card-footer"><span class="small text-body-secondary">{{ trans('ronove::admin.languages.fallbacks_save_help') }}</span><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ trans('messages.actions.save') }}</button></div>
            </div>
        </form>
    @endif
    </div>
@endsection
