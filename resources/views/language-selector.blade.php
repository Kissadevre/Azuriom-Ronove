@inject('ronoveLocales', 'Azuriom\Plugin\Ronove\Services\LocaleManager')

@php($availableRonoveLocales = $ronoveLocales->enabled())

@if($availableRonoveLocales->isNotEmpty())
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-translate" aria-hidden="true"></i>
            <span class="visually-hidden">{{ trans('ronove::messages.language') }}</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach($availableRonoveLocales as $ronoveLocale)
                <li>
                    <form action="{{ route('ronove.locale.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $ronoveLocale->code }}">
                        <button class="dropdown-item @if(app()->getLocale() === $ronoveLocale->code) active @endif" type="submit">
                            {{ $ronoveLocale->native_name }}
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </li>
@endif
