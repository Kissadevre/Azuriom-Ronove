<?php

namespace Azuriom\Plugin\Ronove\Services;

class ReviewWorkflow
{
    public const SETTING_KEY = 'ronove.review_workflow_enabled';

    public function enabled(): bool
    {
        return filter_var(setting(self::SETTING_KEY, false), FILTER_VALIDATE_BOOL);
    }
}
