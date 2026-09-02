@inject('ronoveLanguageSwitcher', 'Azuriom\Plugin\Ronove\Services\LanguageSwitcher')

@php($ronoveLanguageOptions = $languageOptions ?? $ronoveLanguageSwitcher->options())

@if($ronoveLanguageOptions->isEmpty())
    <div class="alert alert-info mb-0">{{ trans('ronove::messages.no_languages') }}</div>
@else
    <div class="row g-3">
        @foreach($ronoveLanguageOptions as $ronoveLanguage)
            <div class="col-sm-6 col-lg-4">
                <form action="{{ $ronoveLanguageSwitcher->updateUrl() }}" method="POST">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $ronoveLanguage->code }}">
                    <button type="submit" class="btn w-100 {{ $ronoveLanguage->isCurrent ? 'btn-primary' : 'btn-outline-primary' }}" @if($ronoveLanguage->isCurrent) aria-current="true" @endif>
                        {{ $ronoveLanguage->nativeName }}
                        @if($ronoveLanguage->isCurrent)
                            <i class="bi bi-check-lg ms-1" aria-hidden="true"></i>
                            <span class="visually-hidden">{{ trans('ronove::messages.current_language') }}</span>
                        @endif
                    </button>
                </form>
            </div>
        @endforeach
    </div>
@endif
