@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.languages.title'))

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="mb-1">{{ trans('ronove::admin.languages.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ trans('ronove::admin.languages.description') }}</p>
        </div>
        <span class="badge text-bg-secondary align-self-start">
            {{ trans('ronove::admin.languages.global', ['locale' => $availableLocales->get($globalLocale, $globalLocale)]) }}
        </span>
    </div>

    <form action="{{ route('ronove.admin.languages.update') }}" method="POST">
        @csrf

        <div class="card mb-4">
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
                            <label class="border rounded p-3 d-flex align-items-center gap-3 h-100" for="locale{{ $loop->index }}">
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

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1" aria-hidden="true"></i> {{ trans('messages.actions.save') }}
        </button>
    </form>
@endsection
