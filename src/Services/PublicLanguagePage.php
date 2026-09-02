<?php

namespace Azuriom\Plugin\Ronove\Services;

class PublicLanguagePage
{
    public const SETTING_KEY = 'ronove.public_language_page_enabled';

    public function enabled(): bool
    {
        return filter_var(setting(self::SETTING_KEY, true), FILTER_VALIDATE_BOOL);
    }
}
