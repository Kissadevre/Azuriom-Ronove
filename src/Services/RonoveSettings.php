<?php

namespace Azuriom\Plugin\Ronove\Services;

class RonoveSettings
{
    public const DEBUG_ENABLED_KEY = 'ronove.debug_enabled';

    public function debugEnabled(): bool
    {
        return filter_var(setting(self::DEBUG_ENABLED_KEY, false), FILTER_VALIDATE_BOOL);
    }
}
