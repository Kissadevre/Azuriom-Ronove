@inject('ronoveLanguageSwitcher', 'Azuriom\Plugin\Ronove\Services\LanguageSwitcher')

@php($ronoveLanguageOptions = $ronoveLanguageSwitcher->options())
@php($currentRonoveLanguage = $ronoveLanguageOptions->first(fn ($option) => $option->isCurrent))
@php($showCurrentName = $showCurrentName ?? true)

@if($ronoveLanguageOptions->isNotEmpty())
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ trans('ronove::messages.language') }}">
            <i class="bi bi-translate" aria-hidden="true"></i>
            @if($showCurrentName && $currentRonoveLanguage !== null)
                <span class="ms-1">{{ $currentRonoveLanguage->nativeName }}</span>
            @else
                <span class="visually-hidden">{{ trans('ronove::messages.language') }}</span>
            @endif
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-label="{{ trans('ronove::messages.available_languages') }}">
            @foreach($ronoveLanguageOptions as $ronoveLanguage)
                <li>
                    <form action="{{ $ronoveLanguageSwitcher->updateUrl() }}" method="POST">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $ronoveLanguage->code }}">
                        <button class="dropdown-item @if($ronoveLanguage->isCurrent) active @endif" type="submit" @if($ronoveLanguage->isCurrent) aria-current="true" @endif>
                            {{ $ronoveLanguage->nativeName }}
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </li>
@endif
