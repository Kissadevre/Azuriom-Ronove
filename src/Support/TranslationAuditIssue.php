<?php

namespace Azuriom\Plugin\Ronove\Support;

final readonly class TranslationAuditIssue
{
    public const EMPTY_RESOURCES = 'empty_resources';

    public const MISSING_PROVIDERS = 'missing_providers';

    public const MISSING_SOURCES = 'missing_sources';

    public const EMPTY_TRANSLATIONS = 'empty_translations';

    public const INVALID_TRANSLATION_VALUES = 'invalid_translation_values';

    public const INVALID_TRANSLATION_STATUSES = 'invalid_translation_statuses';

    public const INVALID_REVIEW_STATUSES = 'invalid_review_statuses';

    public const OUTDATED_TRANSLATIONS = 'outdated_translations';

    public const BLANK_NOTES = 'blank_notes';

    public const UNKNOWN_GLOSSARY_SCOPES = 'unknown_glossary_scopes';

    public const DISABLED_LOCALE_PREFERENCES = 'disabled_locale_preferences';

    public const INVALID_FALLBACKS = 'invalid_fallbacks';

    public const CATEGORIES = [
        self::EMPTY_RESOURCES,
        self::MISSING_PROVIDERS,
        self::MISSING_SOURCES,
        self::EMPTY_TRANSLATIONS,
        self::INVALID_TRANSLATION_VALUES,
        self::INVALID_TRANSLATION_STATUSES,
        self::INVALID_REVIEW_STATUSES,
        self::OUTDATED_TRANSLATIONS,
        self::BLANK_NOTES,
        self::UNKNOWN_GLOSSARY_SCOPES,
        self::DISABLED_LOCALE_PREFERENCES,
        self::INVALID_FALLBACKS,
    ];

    public const CLEANABLE_CATEGORIES = [
        self::EMPTY_RESOURCES,
        self::MISSING_PROVIDERS,
        self::MISSING_SOURCES,
        self::EMPTY_TRANSLATIONS,
        self::INVALID_TRANSLATION_VALUES,
        self::INVALID_TRANSLATION_STATUSES,
        self::INVALID_REVIEW_STATUSES,
        self::BLANK_NOTES,
        self::UNKNOWN_GLOSSARY_SCOPES,
        self::DISABLED_LOCALE_PREFERENCES,
        self::INVALID_FALLBACKS,
    ];

    public function __construct(
        public string $category,
        public string $recordType,
        public int $recordId,
        public string $reference,
        public ?string $locale = null,
        public ?string $details = null,
    ) {}

    public function isCleanable(): bool
    {
        return in_array($this->category, self::CLEANABLE_CATEGORIES, true);
    }
}
