<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    public function index(Request $request, ResourceRegistry $registry)
    {
        abort_if($registry->all()->isEmpty(), 404);

        $type = $request->string('type')->toString() ?: $registry->all()->keys()->first();
        $provider = $this->provider($registry, $type);
        $this->authorizeProvider($provider);
        $resources = $provider->query()->paginate(20)->withQueryString();
        $storedResources = Resource::query()
            ->where('resource_type', $provider->type())
            ->whereIn('resource_key', collect($resources->items())->map(
                fn (Model $model) => $provider->key($model)
            ))
            ->with(['translations.locale'])
            ->get()
            ->keyBy('resource_key');

        return view('ronove::admin.translations.index', [
            'providers' => $registry->all(),
            'provider' => $provider,
            'resources' => $resources,
            'storedResources' => $storedResources,
            'locales' => Locale::query()->where('is_enabled', true)->orderBy('position')->get(),
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
        $this->authorizeProvider($provider);
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
        $this->authorizeProvider($provider);
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

        DB::transaction(function () use ($provider, $model, $locale, $validated, $values, $resolver) {
            $resource = Resource::query()->firstOrCreate([
                'resource_type' => $provider->type(),
                'resource_key' => $provider->key($model),
            ]);

            Translation::query()->updateOrCreate(
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
        });

        ActionLog::log('ronove.translations.saved', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
        ]);

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
        $this->authorizeProvider($provider);
        $model = $this->model($provider, $key);
        $resource = Resource::query()
            ->where('resource_type', $provider->type())
            ->where('resource_key', $provider->key($model))
            ->first();

        $resource?->translations()->where('locale_id', $locale->id)->delete();

        if ($resource !== null && ! $resource->translations()->exists()) {
            $resource->delete();
        }

        ActionLog::log('ronove.translations.deleted', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
        ]);

        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.translations.deleted'));
    }

    private function provider(ResourceRegistry $registry, string $type): ResourceProvider
    {
        abort_unless($registry->has($type), 404);

        return $registry->get($type);
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
}
