@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.languages.title'))

@include('ronove::admin._assets')

@section('content')
    <div class="ronove-admin-shell">
    @include('ronove::admin._header', [
        'title' => trans('ronove::admin.languages.title'),
        'description' => trans('ronove::admin.languages.description'),
        'icon' => 'bi-globe2',
    ])

    <div class="alert alert-info d-flex align-items-center gap-2" role="status">
        <i class="bi bi-translate" aria-hidden="true"></i>
        <span>{{ trans('ronove::admin.languages.global', ['locale' => $availableLocales->get($globalLocale, $globalLocale)]) }}</span>
    </div>

    <form action="{{ route('ronove.admin.languages.update') }}" method="POST">
        @csrf

        <div class="card ronove-admin-card mb-4">
            <div class="card-body">
                @error('locales')
                    <div class="alert alert-danger" role="alert"><strong>{{ $message }}</strong></div>
                @enderror

                <div class="row g-3">
                    @foreach($availableLocales as $code => $name)
                        @php($normalizedCode = \Azuriom\Plugin\Ronove\Support\LocaleCode::normalize($code))
                        @php($enabled = old('locales') !== null
                            ? in_array($code, old('locales', []), true)
                            : ($configuredLocales->get($normalizedCode)?->is_enabled ?? $normalizedCode === $globalLocale))
                        <div class="col-md-6 col-xl-4">
                            <label class="ronove-locale-option border rounded p-3 d-flex align-items-center gap-3 h-100" for="locale{{ $loop->index }}">
                                <input class="form-check-input mt-0" id="locale{{ $loop->index }}" type="checkbox" name="locales[]" value="{{ $code }}" @checked($enabled)>
                                <span>
                                    <strong class="d-block">{{ $name }}</strong>
                                    <small class="text-body-secondary">{{ $normalizedCode }}</small>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mb-2">
            <i class="bi bi-save me-1" aria-hidden="true"></i> {{ trans('messages.actions.save') }}
        </button>
    </form>

    @if($enabledLocales->isNotEmpty())
        <hr class="my-5">

        <div class="mb-4">
            <h2 class="h4 mb-1">{{ trans('ronove::admin.languages.fallbacks_title') }}</h2>
            <p class="text-body-secondary mb-0">{{ trans('ronove::admin.languages.fallbacks_description') }}</p>
        </div>

        <form action="{{ route('ronove.admin.languages.fallbacks.update') }}" method="POST">
            @csrf

            <div class="card ronove-admin-card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($enabledLocales as $locale)
                            <div class="col-md-6">
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
                        @endforeach
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1" aria-hidden="true"></i> {{ trans('messages.actions.save') }}
            </button>
        </form>
    @endif
    </div>
@endsection
