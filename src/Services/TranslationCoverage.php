<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Support\TranslationCoverageReport;
use Illuminate\Database\Eloquent\Model;

class TranslationCoverage
{
    public function __construct(private readonly TranslationResolver $resolver) {}

    public function report(ResourceProvider $provider, Locale $locale): TranslationCoverageReport
    {
        $models = $provider->query()
            ->get()
            ->keyBy(fn (Model $model) => $provider->key($model));
        $records = $models->isEmpty()
            ? collect()
            : Resource::query()
                ->where('resource_type', $provider->type())
                ->whereIn('resource_key', $models->keys())
                ->with(['translations' => fn ($query) => $query->where('locale_id', $locale->id)])
                ->get()
                ->keyBy('resource_key');
        $statuses = [];
        $outdated = [];

        foreach ($models as $key => $model) {
            $translation = $records->get($key)?->translations->first();
            $statuses[$key] = $translation?->hasPublishedVersion()
                ? Translation::PUBLISHED
                : ($translation?->status ?? TranslationCoverageReport::MISSING);
            $storedSourceHash = $translation?->hasPublishedVersion()
                ? ($translation->published_source_hash ?? $translation->source_hash)
                : $translation?->source_hash;
            $outdated[$key] = $translation instanceof Translation
                && $storedSourceHash !== $this->resolver->sourceHash($provider, $model);
        }

        return new TranslationCoverageReport($statuses, $outdated);
    }
}
