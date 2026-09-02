<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Support\LocaleOption;
use Illuminate\Support\Collection;

class LanguageSwitcher
{
    public function __construct(private readonly LocaleManager $locales) {}

    /**
     * Return presentation-safe language options for themes and plugins.
     *
     * @return Collection<int, LocaleOption>
     */
    public function options(?string $currentLocale = null): Collection
    {
        $currentLocale ??= app()->getLocale();
        $global = $this->locales->globalLocale();
        $globalName = trans('messages.lang', [], $global);
        $original = new LocaleOption(
            code: $global,
            name: $globalName,
            nativeName: trans('ronove::messages.original_language', ['locale' => $globalName]),
            isCurrent: $global === $currentLocale,
        );

        return $this->locales->enabled()
            ->map(fn ($locale) => new LocaleOption(
                code: $locale->code,
                name: $locale->name,
                nativeName: $locale->native_name,
                isCurrent: $locale->code === $currentLocale,
            ))
            ->prepend($original)
            ->values();
    }

    public function current(): ?LocaleOption
    {
        return $this->options()->first(fn (LocaleOption $option) => $option->isCurrent);
    }

    public function updateUrl(): string
    {
        return route('ronove.locale.update');
    }
}
