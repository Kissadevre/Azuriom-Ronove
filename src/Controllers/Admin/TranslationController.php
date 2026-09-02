<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Contracts\FilterableResourceProvider;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Events\TranslationDeleted;
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Azuriom\Plugin\Ronove\Events\TranslationSaved;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationCoverage;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Support\TranslationCoverageReport;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    public function index(ResourceRegistry $registry)
    {
        abort_if($registry->all()->isEmpty(), 404);

        $integrationGroups = $registry->integrations()
            ->filter(fn (TranslationIntegration $integration) => $this->canAccessIntegration($integration))
            ->map(function (TranslationIntegration $integration) use ($registry) {
                return [
                    'integration' => $integration,
                    'providers' => $this->accessibleProviders($registry, $integration->id),
                ];
            })
            ->filter(fn (array $group) => $group['providers']->isNotEmpty());

        return view('ronove::admin.translations.index', [
            'integrationGroups' => $integrationGroups,
        ]);
    }

    public function integration(
        Request $request,
        ResourceRegistry $registry,
        TranslationCoverage $coverage,
        string $integration,
    ) {
        abort_unless($registry->hasIntegration($integration), 404);

        $integrationDefinition = $registry->integration($integration);
        $this->authorizeIntegration($integrationDefinition);
        $providers = $this->accessibleProviders($registry, $integration);
        abort_if($providers->isEmpty(), 403);

        $type = $request->string('type')->toString() ?: $providers->keys()->first();
        abort_unless($providers->has($type), 404);

        $provider = $providers->get($type);
        $validated = $request->validate([
            'locale' => [
                'nullable', 'string',
                Rule::exists('ronove_locales', 'code')->where('is_enabled', true),
            ],
            'status' => ['nullable', 'string', Rule::in(TranslationCoverageReport::FILTERS)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $locales = Locale::query()->where('is_enabled', true)->orderBy('position')->get();
        $selectedLocale = isset($validated['locale'])
            ? $locales->firstWhere('code', $validated['locale'])
            : $locales->first();
        $status = $validated['status'] ?? null;
        $search = trim($validated['search'] ?? '');
        $filterable = $provider instanceof FilterableResourceProvider;

        abort_if(! $filterable && ($status !== null || $search !== ''), 422);
        abort_if($status !== null && $selectedLocale === null, 422);

        $report = $selectedLocale === null ? null : $coverage->report($provider, $selectedLocale);
        $query = $provider->query();

        if ($filterable && $search !== '') {
            $provider->applySearch($query, $search);
        }

        if ($filterable && $status !== null && $report !== null) {
            $provider->applyResourceKeys($query, $report->keysFor($status));
        }

        $resources = $query->paginate(20)->withQueryString();
        $storedResources = Resource::query()
            ->where('resource_type', $provider->type())
            ->whereIn('resource_key', collect($resources->items())->map(
                fn (Model $model) => $provider->key($model)
            ))
            ->with(['translations.locale'])
            ->get()
            ->keyBy('resource_key');

        return view('ronove::admin.translations.integration', [
            'integration' => $integrationDefinition,
            'providers' => $providers,
            'provider' => $provider,
            'resources' => $resources,
            'storedResources' => $storedResources,
            'locales' => $locales,
            'selectedLocale' => $selectedLocale,
            'coverage' => $report,
            'filterable' => $filterable,
            'statusFilter' => $status,
            'searchFilter' => $search,
        ]);
    }

    public function edit(
        Request $request,
        ResourceRegistry $registry,
        string $type,
        string $key,
        TranslationResolver $resolver,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        $locales = Locale::query()->where('is_enabled', true)->orderBy('position')->get();
        $selectedCode = $request->string('locale')->toString();
        $showOriginal = $selectedCode === '' || $selectedCode === 'original';
        $selectedLocale = $showOriginal ? null : $locales->firstWhere('code', $selectedCode);

        if (! $showOriginal && $selectedLocale === null) {
            abort(404);
        }
        $resource = Resource::query()
            ->where('resource_type', $provider->type())
            ->where('resource_key', $provider->key($model))
            ->with(['translations.locale'])
            ->first();
        $translation = $selectedLocale === null
            ? null
            : $resource?->translations->firstWhere('locale_id', $selectedLocale->id);

        return view('ronove::admin.translations.edit', [
            'integration' => $registry->integrationFor($provider->type()),
            'provider' => $provider,
            'resourceModel' => $model,
            'resourceRecord' => $resource,
            'locales' => $locales,
            'selectedLocale' => $selectedLocale,
            'showOriginal' => $showOriginal,
            'translation' => $translation,
            'sourceHash' => $resolver->sourceHash($provider, $model),
            'canPublish' => Gate::allows('ronove.publish'),
        ]);
    }

    public function update(
        Request $request,
        ResourceRegistry $registry,
        string $type,
        string $key,
        TranslationResolver $resolver,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        $rules = [
            'locale' => [
                'required', 'string',
                Rule::exists('ronove_locales', 'code')->where('is_enabled', true),
            ],
            'status' => ['required', Rule::in([Translation::DRAFT, Translation::PUBLISHED])],
            'values' => ['nullable', 'array'],
        ];

        foreach ($provider->fields() as $field => $definition) {
            $rules['values.'.$field] = array_filter([
                'nullable',
                'string',
                $definition->maxLength === null ? null : 'max:'.$definition->maxLength,
            ]);
        }

        $validated = $request->validate($rules);

        if ($validated['status'] === Translation::PUBLISHED) {
            Gate::authorize('ronove.publish');
        }

        $locale = Locale::query()->where('code', $validated['locale'])->firstOrFail();
        $values = collect($validated['values'] ?? [])
            ->only(array_keys($provider->fields()))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->all();

        [$translation, $previousStatus] = DB::transaction(function () use ($provider, $model, $locale, $validated, $values, $resolver) {
            $resource = Resource::query()->firstOrCreate([
                'resource_type' => $provider->type(),
                'resource_key' => $provider->key($model),
            ]);
            $existing = Translation::query()
                ->where('resource_id', $resource->id)
                ->where('locale_id', $locale->id)
                ->first();

            $translation = Translation::query()->updateOrCreate(
                [
                    'resource_id' => $resource->id,
                    'locale_id' => $locale->id,
                ],
                [
                    'status' => $validated['status'],
                    'values' => $values,
                    'source_hash' => $resolver->sourceHash($provider, $model),
                ],
            );

            return [$translation, $existing?->status];
        });

        ActionLog::log('ronove.translations.saved', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
        ]);
        TranslationSaved::dispatch(
            $provider->type(),
            $provider->key($model),
            $locale->code,
            $translation->status,
            $translation->values,
            $previousStatus,
        );

        if ($translation->isPublished() && $previousStatus !== Translation::PUBLISHED) {
            TranslationPublished::dispatch(
                $provider->type(),
                $provider->key($model),
                $locale->code,
                $translation->values,
                $previousStatus,
            );
        }

        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.translations.updated'));
    }

    public function destroy(
        ResourceRegistry $registry,
        string $type,
        string $key,
        Locale $locale,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        $resource = Resource::query()
            ->where('resource_type', $provider->type())
            ->where('resource_key', $provider->key($model))
            ->first();

        $translation = $resource?->translations()->where('locale_id', $locale->id)->first();
        $translation?->delete();

        if ($resource !== null && ! $resource->translations()->exists()) {
            $resource->delete();
        }

        ActionLog::log('ronove.translations.deleted', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
        ]);

        if ($translation !== null) {
            TranslationDeleted::dispatch(
                $provider->type(),
                $provider->key($model),
                $locale->code,
                $translation->status,
            );
        }

        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.translations.deleted'));
    }

    private function provider(ResourceRegistry $registry, string $type): ResourceProvider
    {
        abort_unless($registry->has($type), 404);

        $provider = $registry->get($type);
        $this->authorizeIntegration($registry->integrationFor($type));
        $this->authorizeProvider($provider);

        return $provider;
    }

    private function model(ResourceProvider $provider, string $key): Model
    {
        return $provider->find($key) ?? abort(404);
    }

    private function authorizeProvider(ResourceProvider $provider): void
    {
        if ($provider->permission() !== null) {
            Gate::authorize($provider->permission());
        }
    }

    private function authorizeIntegration(TranslationIntegration $integration): void
    {
        if ($integration->permission !== null) {
            Gate::authorize($integration->permission);
        }
    }

    private function canAccessIntegration(TranslationIntegration $integration): bool
    {
        return $integration->permission === null || Gate::allows($integration->permission);
    }

    /**
     * @return Collection<string, ResourceProvider>
     */
    private function accessibleProviders(ResourceRegistry $registry, string $integration): Collection
    {
        return $registry->forIntegration($integration)
            ->filter(fn (ResourceProvider $provider) => $provider->permission() === null
                || Gate::allows($provider->permission()));
    }
}
