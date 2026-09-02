<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Events\TranslationDeleted;
use Azuriom\Plugin\Ronove\Models\GlossaryTerm;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Models\TranslationNote;
use Azuriom\Plugin\Ronove\Models\UserPreference;
use Azuriom\Plugin\Ronove\Support\TranslationAuditIssue;
use Azuriom\Plugin\Ronove\Support\TranslationAuditReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TranslationAudit
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly TranslationResolver $resolver,
    ) {}

    public function inspect(): TranslationAuditReport
    {
        $issues = collect();

        $this->inspectResources($issues);
        $this->inspectGlossary($issues);
        $this->inspectPreferences($issues);
        $this->inspectFallbacks($issues);

        return new TranslationAuditReport($issues);
    }

    public function cleanup(string $category): int
    {
        if (! in_array($category, TranslationAuditIssue::CLEANABLE_CATEGORIES, true)) {
            throw new InvalidArgumentException("Unsupported Ronove cleanup category [{$category}].");
        }

        $issues = $this->inspect()->issuesFor($category);

        if ($issues->isEmpty()) {
            return 0;
        }

        $deletedTranslations = [];
        $count = DB::transaction(function () use ($category, $issues, &$deletedTranslations) {
            return match ($category) {
                TranslationAuditIssue::EMPTY_RESOURCES,
                TranslationAuditIssue::MISSING_PROVIDERS,
                TranslationAuditIssue::MISSING_SOURCES => $this->deleteResources($category, $issues, $deletedTranslations),
                TranslationAuditIssue::EMPTY_TRANSLATIONS => $this->deleteTranslations($issues, $deletedTranslations),
                TranslationAuditIssue::INVALID_TRANSLATION_VALUES => $this->sanitizeTranslations($issues, $deletedTranslations),
                TranslationAuditIssue::INVALID_TRANSLATION_STATUSES => $this->normalizeTranslationStatuses($issues),
                TranslationAuditIssue::BLANK_NOTES => $this->deleteBlankNotes($issues),
                TranslationAuditIssue::UNKNOWN_GLOSSARY_SCOPES => $this->deleteUnknownGlossaryTerms($issues),
                TranslationAuditIssue::DISABLED_LOCALE_PREFERENCES => $this->deleteDisabledPreferences($issues),
                TranslationAuditIssue::INVALID_FALLBACKS => $this->resetInvalidFallbacks($issues),
            };
        });

        foreach ($deletedTranslations as $payload) {
            TranslationDeleted::dispatch(...$payload);
        }

        return $count;
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function inspectResources(Collection $issues): void
    {
        Resource::query()
            ->with(['translations.locale', 'notes.locale'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $resources) use ($issues) {
                foreach ($resources as $resource) {
                    if ($resource->translations->isEmpty() && $resource->notes->isEmpty()) {
                        $issues->push($this->resourceIssue(
                            TranslationAuditIssue::EMPTY_RESOURCES,
                            $resource,
                        ));

                        continue;
                    }

                    if (! $this->registry->has($resource->resource_type)) {
                        $issues->push($this->resourceIssue(
                            TranslationAuditIssue::MISSING_PROVIDERS,
                            $resource,
                        ));

                        continue;
                    }

                    $provider = $this->registry->get($resource->resource_type);
                    $model = $provider->find($resource->resource_key);

                    if ($model === null) {
                        $issues->push($this->resourceIssue(
                            TranslationAuditIssue::MISSING_SOURCES,
                            $resource,
                        ));

                        continue;
                    }

                    $sourceHash = $this->resolver->sourceHash($provider, $model);

                    foreach ($resource->translations as $translation) {
                        $this->inspectTranslation($issues, $provider, $resource, $translation, $sourceHash);
                    }

                    foreach ($resource->notes as $note) {
                        if (trim($note->note) === '') {
                            $issues->push(new TranslationAuditIssue(
                                TranslationAuditIssue::BLANK_NOTES,
                                'note',
                                $note->id,
                                $this->resourceReference($resource),
                                $note->locale?->code,
                            ));
                        }
                    }
                }
            });
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function inspectTranslation(
        Collection $issues,
        ResourceProvider $provider,
        Resource $resource,
        Translation $translation,
        string $sourceHash,
    ): void {
        $normalized = $this->normalizedValues($translation, $provider);
        $invalidFields = $this->invalidFields($translation, $provider);
        $locale = $translation->locale?->code;
        $reference = $this->resourceReference($resource);

        if ($normalized === []) {
            $issues->push(new TranslationAuditIssue(
                TranslationAuditIssue::EMPTY_TRANSLATIONS,
                'translation',
                $translation->id,
                $reference,
                $locale,
            ));
        }

        if ($invalidFields !== []) {
            $issues->push(new TranslationAuditIssue(
                TranslationAuditIssue::INVALID_TRANSLATION_VALUES,
                'translation',
                $translation->id,
                $reference,
                $locale,
                implode(', ', $invalidFields),
            ));
        }

        if (! in_array($translation->status, [Translation::DRAFT, Translation::PUBLISHED], true)) {
            $issues->push(new TranslationAuditIssue(
                TranslationAuditIssue::INVALID_TRANSLATION_STATUSES,
                'translation',
                $translation->id,
                $reference,
                $locale,
                $translation->status,
            ));
        }

        if ($normalized !== [] && $translation->source_hash !== $sourceHash) {
            $issues->push(new TranslationAuditIssue(
                TranslationAuditIssue::OUTDATED_TRANSLATIONS,
                'translation',
                $translation->id,
                $reference,
                $locale,
            ));
        }
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function inspectGlossary(Collection $issues): void
    {
        GlossaryTerm::query()
            ->with('locale')
            ->where('scope', '!=', GlossaryTerm::GLOBAL_SCOPE)
            ->orderBy('id')
            ->chunkById(100, function (Collection $terms) use ($issues) {
                foreach ($terms as $term) {
                    if ($this->registry->hasIntegration($term->scope)) {
                        continue;
                    }

                    $issues->push(new TranslationAuditIssue(
                        TranslationAuditIssue::UNKNOWN_GLOSSARY_SCOPES,
                        'glossary',
                        $term->id,
                        $term->source_text,
                        $term->locale?->code,
                        $term->scope,
                    ));
                }
            });
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function inspectPreferences(Collection $issues): void
    {
        UserPreference::query()
            ->with('locale')
            ->whereHas('locale', fn ($query) => $query->where('is_enabled', false))
            ->orderBy('id')
            ->chunkById(100, function (Collection $preferences) use ($issues) {
                foreach ($preferences as $preference) {
                    $issues->push(new TranslationAuditIssue(
                        TranslationAuditIssue::DISABLED_LOCALE_PREFERENCES,
                        'preference',
                        $preference->id,
                        '#'.$preference->user_id,
                        $preference->locale?->code,
                    ));
                }
            });
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function inspectFallbacks(Collection $issues): void
    {
        $locales = Locale::query()->orderBy('id')->get();
        $byId = $locales->keyBy('id');

        foreach ($locales as $locale) {
            if ($locale->fallback_locale_id === null) {
                continue;
            }

            $reason = $this->invalidFallbackReason($locale, $byId);

            if ($reason === null) {
                continue;
            }

            $issues->push(new TranslationAuditIssue(
                TranslationAuditIssue::INVALID_FALLBACKS,
                'locale',
                $locale->id,
                $locale->code,
                $locale->code,
                $reason,
            ));
        }
    }

    /**
     * @param  Collection<int, Locale>  $locales
     */
    private function invalidFallbackReason(Locale $locale, Collection $locales): ?string
    {
        if (! $locale->is_enabled) {
            return 'disabled_source';
        }

        $visited = [];
        $current = $locale;

        while ($current->fallback_locale_id !== null) {
            if (isset($visited[$current->id])) {
                return 'cycle';
            }

            $visited[$current->id] = true;
            $fallback = $locales->get($current->fallback_locale_id);

            if (! $fallback instanceof Locale || ! $fallback->is_enabled) {
                return 'disabled_target';
            }

            $current = $fallback;
        }

        return null;
    }

    private function resourceIssue(string $category, Resource $resource): TranslationAuditIssue
    {
        return new TranslationAuditIssue(
            $category,
            'resource',
            $resource->id,
            $this->resourceReference($resource),
        );
    }

    private function resourceReference(Resource $resource): string
    {
        return $resource->resource_type.':'.$resource->resource_key;
    }

    /**
     * @return array<string, string>
     */
    private function normalizedValues(Translation $translation, ResourceProvider $provider): array
    {
        $fields = $provider->fields();
        $values = is_array($translation->values) ? $translation->values : [];
        $normalized = [];

        foreach ($values as $field => $value) {
            if (! is_string($field)
                || ! array_key_exists($field, $fields)
                || ! is_string($value)
                || trim($value) === '') {
                continue;
            }

            $normalized[$field] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function invalidFields(Translation $translation, ResourceProvider $provider): array
    {
        $fields = $provider->fields();
        $values = is_array($translation->values) ? $translation->values : [];
        $invalid = [];

        foreach ($values as $field => $value) {
            if (! is_string($field)
                || ! array_key_exists($field, $fields)
                || ! is_string($value)
                || trim($value) === '') {
                $invalid[] = (string) $field;
            }
        }

        return array_values(array_unique($invalid));
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     * @param  array<int, array{string, string, string, string}>  $deletedTranslations
     */
    private function deleteResources(
        string $category,
        Collection $issues,
        array &$deletedTranslations,
    ): int {
        $resources = Resource::query()
            ->with(['translations.locale', 'notes'])
            ->whereKey($issues->pluck('recordId'))
            ->lockForUpdate()
            ->get();
        $count = 0;

        foreach ($resources as $resource) {
            if (! $this->resourceMatchesCategory($resource, $category)) {
                continue;
            }

            foreach ($resource->translations as $translation) {
                $deletedTranslations[] = $this->deletedEventPayload($resource, $translation);
            }

            $resource->delete();
            $count++;
        }

        return $count;
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     * @param  array<int, array{string, string, string, string}>  $deletedTranslations
     */
    private function deleteTranslations(Collection $issues, array &$deletedTranslations): int
    {
        $translations = Translation::query()
            ->with(['resource', 'locale'])
            ->whereKey($issues->pluck('recordId'))
            ->lockForUpdate()
            ->get();
        $resourceIds = $translations->pluck('resource_id')->unique();
        $count = 0;

        foreach ($translations as $translation) {
            $resource = $translation->resource;

            if ($resource === null || ! $this->registry->has($resource->resource_type)) {
                continue;
            }

            $provider = $this->registry->get($resource->resource_type);

            if ($provider->find($resource->resource_key) === null
                || $this->normalizedValues($translation, $provider) !== []) {
                continue;
            }

            $deletedTranslations[] = $this->deletedEventPayload($resource, $translation);
            $translation->delete();
            $count++;
        }

        $this->deleteEmptyResources($resourceIds);

        return $count;
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     * @param  array<int, array{string, string, string, string}>  $deletedTranslations
     */
    private function sanitizeTranslations(Collection $issues, array &$deletedTranslations): int
    {
        $translations = Translation::query()
            ->with(['resource', 'locale'])
            ->whereKey($issues->pluck('recordId'))
            ->lockForUpdate()
            ->get();
        $resourceIds = collect();
        $count = 0;

        foreach ($translations as $translation) {
            $resource = $translation->resource;

            if ($resource === null || ! $this->registry->has($resource->resource_type)) {
                continue;
            }

            $provider = $this->registry->get($resource->resource_type);

            if ($provider->find($resource->resource_key) === null
                || $this->invalidFields($translation, $provider) === []) {
                continue;
            }

            $values = $this->normalizedValues($translation, $provider);

            if ($values === []) {
                $deletedTranslations[] = $this->deletedEventPayload($resource, $translation);
                $resourceIds->push($translation->resource_id);
                $translation->delete();
            } else {
                $translation->update(['values' => $values]);
            }

            $count++;
        }

        $this->deleteEmptyResources($resourceIds->unique());

        return $count;
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function normalizeTranslationStatuses(Collection $issues): int
    {
        return Translation::query()
            ->whereKey($issues->pluck('recordId'))
            ->whereNotIn('status', [Translation::DRAFT, Translation::PUBLISHED])
            ->lockForUpdate()
            ->update(['status' => Translation::DRAFT]);
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function deleteBlankNotes(Collection $issues): int
    {
        $notes = TranslationNote::query()
            ->whereKey($issues->pluck('recordId'))
            ->lockForUpdate()
            ->get()
            ->filter(fn (TranslationNote $note) => trim($note->note) === '');
        $resourceIds = $notes->pluck('resource_id')->unique();

        TranslationNote::query()->whereKey($notes->pluck('id'))->delete();
        $this->deleteEmptyResources($resourceIds);

        return $notes->count();
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function deleteUnknownGlossaryTerms(Collection $issues): int
    {
        $terms = GlossaryTerm::query()
            ->whereKey($issues->pluck('recordId'))
            ->lockForUpdate()
            ->get()
            ->filter(fn (GlossaryTerm $term) => $term->scope !== GlossaryTerm::GLOBAL_SCOPE
                && ! $this->registry->hasIntegration($term->scope));

        GlossaryTerm::query()->whereKey($terms->pluck('id'))->delete();

        return $terms->count();
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function deleteDisabledPreferences(Collection $issues): int
    {
        $preferences = UserPreference::query()
            ->with('locale')
            ->whereKey($issues->pluck('recordId'))
            ->lockForUpdate()
            ->get()
            ->filter(fn (UserPreference $preference) => $preference->locale?->is_enabled === false);

        UserPreference::query()->whereKey($preferences->pluck('id'))->delete();

        return $preferences->count();
    }

    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    private function resetInvalidFallbacks(Collection $issues): int
    {
        $locales = Locale::query()->lockForUpdate()->get();
        $byId = $locales->keyBy('id');
        $affected = $locales
            ->whereIn('id', $issues->pluck('recordId'))
            ->filter(fn (Locale $locale) => $locale->fallback_locale_id !== null
                && $this->invalidFallbackReason($locale, $byId) !== null);

        foreach ($affected as $locale) {
            $locale->update(['fallback_locale_id' => null]);
        }

        return $affected->count();
    }

    private function resourceMatchesCategory(Resource $resource, string $category): bool
    {
        if ($category === TranslationAuditIssue::EMPTY_RESOURCES) {
            return $resource->translations->isEmpty() && $resource->notes->isEmpty();
        }

        if ($category === TranslationAuditIssue::MISSING_PROVIDERS) {
            return ! $this->registry->has($resource->resource_type);
        }

        if ($category === TranslationAuditIssue::MISSING_SOURCES
            && $this->registry->has($resource->resource_type)) {
            return $this->registry->get($resource->resource_type)->find($resource->resource_key) === null;
        }

        return false;
    }

    /**
     * @param  Collection<int, int>  $resourceIds
     */
    private function deleteEmptyResources(Collection $resourceIds): void
    {
        if ($resourceIds->isEmpty()) {
            return;
        }

        Resource::query()
            ->whereKey($resourceIds)
            ->whereDoesntHave('translations')
            ->whereDoesntHave('notes')
            ->delete();
    }

    /**
     * @return array{string, string, string, string}
     */
    private function deletedEventPayload(Resource $resource, Translation $translation): array
    {
        return [
            $resource->resource_type,
            $resource->resource_key,
            $translation->locale?->code ?? '#'.$translation->locale_id,
            $translation->status,
        ];
    }
}
