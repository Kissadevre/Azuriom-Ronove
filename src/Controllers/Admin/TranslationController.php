<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Contracts\FilterableResourceProvider;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Events\TranslationDeleted;
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Azuriom\Plugin\Ronove\Events\TranslationSaved;
use Azuriom\Plugin\Ronove\Events\TranslationSubmittedForReview;
use Azuriom\Plugin\Ronove\Models\GlossaryTerm;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Models\TranslationNote;
use Azuriom\Plugin\Ronove\Models\TranslationRevision;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\ReviewWorkflow;
use Azuriom\Plugin\Ronove\Services\TranslationCoverage;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Services\TranslationRevisionRecorder;
use Azuriom\Plugin\Ronove\Support\TranslationCoverageReport;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        ReviewWorkflow $workflow,
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
                Rule::exists('ronove_locales', 'code')->where(fn ($query) => $query
                    ->where('is_enabled', true)
                    ->where('code', '!=', Locale::globalCode())),
            ],
            'status' => ['nullable', 'string', Rule::in(TranslationCoverageReport::FILTERS)],
            'review_status' => ['nullable', 'string', Rule::in(Translation::REVIEW_STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $locales = Locale::query()->where('is_enabled', true)->translationTargets()->orderBy('position')->get();
        $selectedLocale = isset($validated['locale'])
            ? $locales->firstWhere('code', $validated['locale'])
            : $locales->first();
        $status = $validated['status'] ?? null;
        $reviewStatus = $validated['review_status'] ?? null;
        $search = trim($validated['search'] ?? '');
        $filterable = $provider instanceof FilterableResourceProvider;
        $reviewWorkflowEnabled = $workflow->enabled();

        abort_if(! $filterable && ($status !== null || $reviewStatus !== null || $search !== ''), 422);
        abort_if(($status !== null || $reviewStatus !== null) && $selectedLocale === null, 422);
        abort_if($reviewStatus !== null && ! $reviewWorkflowEnabled, 422);

        $report = $selectedLocale === null ? null : $coverage->report($provider, $selectedLocale);
        $query = $provider->query();

        if ($filterable && $search !== '') {
            $provider->applySearch($query, $search);
        }

        if ($filterable && $status !== null && $report !== null) {
            $provider->applyResourceKeys($query, $report->keysFor($status));
        }

        if ($filterable && $reviewStatus !== null && $selectedLocale !== null) {
            $keys = Resource::query()
                ->where('resource_type', $provider->type())
                ->whereHas('translations', fn ($translationQuery) => $translationQuery
                    ->where('locale_id', $selectedLocale->id)
                    ->where('review_status', $reviewStatus))
                ->pluck('resource_key');
            $provider->applyResourceKeys($query, $keys);
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
            'reviewStatusFilter' => $reviewStatus,
            'searchFilter' => $search,
            'reviewWorkflowEnabled' => $reviewWorkflowEnabled,
        ]);
    }

    public function edit(
        Request $request,
        ResourceRegistry $registry,
        string $type,
        string $key,
        TranslationResolver $resolver,
        ReviewWorkflow $workflow,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);

        return $this->editorView($request, $registry, $provider, $model, $resolver, $workflow);
    }

    public function preview(
        Request $request,
        ResourceRegistry $registry,
        string $type,
        string $key,
        TranslationResolver $resolver,
        ReviewWorkflow $workflow,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        $validated = $request->validate($this->translationRules($provider, $workflow->enabled()));
        $values = $this->validatedValues($provider, $validated);

        return $this->editorView(
            $request,
            $registry,
            $provider,
            $model,
            $resolver,
            $workflow,
            $values,
            $validated['status'] ?? Translation::DRAFT,
            true,
        );
    }

    private function editorView(
        Request $request,
        ResourceRegistry $registry,
        ResourceProvider $provider,
        Model $model,
        TranslationResolver $resolver,
        ReviewWorkflow $workflow,
        ?array $editorValues = null,
        ?string $formStatus = null,
        bool $previewGenerated = false,
    ) {
        $locales = Locale::query()->where('is_enabled', true)->translationTargets()->orderBy('position')->get();
        $selectedCode = $request->string('locale')->toString();
        $showOriginal = $selectedCode === '' || $selectedCode === 'original';
        $selectedLocale = $showOriginal ? null : $locales->firstWhere('code', $selectedCode);

        if (! $showOriginal && $selectedLocale === null) {
            abort(404);
        }
        $resource = Resource::query()
            ->where('resource_type', $provider->type())
            ->where('resource_key', $provider->key($model))
            ->with(['translations.locale', 'translations.reviewer', 'notes.locale'])
            ->first();
        $translation = $selectedLocale === null
            ? null
            : $resource?->translations->firstWhere('locale_id', $selectedLocale->id);
        $note = $selectedLocale === null
            ? null
            : $resource?->notes->firstWhere('locale_id', $selectedLocale->id);
        $editorValues ??= $translation?->values ?? [];
        $formStatus ??= $translation?->status ?? Translation::DRAFT;
        $integration = $registry->integrationFor($provider->type());
        $reviewWorkflowEnabled = $workflow->enabled();
        $revisions = $translation?->revisions()
            ->with('user')
            ->latest('id')
            ->paginate(10, ['*'], 'revision_page')
            ->withQueryString();

        return view('ronove::admin.translations.edit', [
            'integration' => $integration,
            'provider' => $provider,
            'resourceModel' => $model,
            'resourceRecord' => $resource,
            'locales' => $locales,
            'selectedLocale' => $selectedLocale,
            'showOriginal' => $showOriginal,
            'translation' => $translation,
            'translationNote' => $note,
            'sourceHash' => $resolver->sourceHash($provider, $model),
            'canPublish' => Gate::allows('ronove.publish'),
            'canReview' => Gate::allows('ronove.review'),
            'reviewWorkflowEnabled' => $reviewWorkflowEnabled,
            'revisions' => $revisions,
            'editorValues' => $editorValues,
            'formStatus' => $formStatus,
            'previewGenerated' => $previewGenerated,
            'previewFields' => $selectedLocale === null
                ? []
                : $resolver->preview($provider->type(), $model, $selectedLocale->code, $editorValues),
            'glossaryTerms' => $selectedLocale === null
                ? collect()
                : $this->glossarySuggestions($integration, $provider, $model, $selectedLocale),
        ]);
    }

    public function update(
        Request $request,
        ResourceRegistry $registry,
        string $type,
        string $key,
        TranslationResolver $resolver,
        ReviewWorkflow $workflow,
        TranslationRevisionRecorder $revisions,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        $reviewWorkflowEnabled = $workflow->enabled();
        $validated = $request->validate($this->translationRules($provider, $reviewWorkflowEnabled, true));
        $workflowAction = $reviewWorkflowEnabled ? $validated['workflow_action'] : null;
        $publishFromReview = $reviewWorkflowEnabled && $workflowAction === 'publish';
        $status = $reviewWorkflowEnabled
            ? ($publishFromReview ? Translation::PUBLISHED : Translation::DRAFT)
            : $validated['status'];

        if ($publishFromReview) {
            Gate::authorize('ronove.review');
            Gate::authorize('ronove.publish');
        }

        if (! $reviewWorkflowEnabled && $status === Translation::PUBLISHED) {
            Gate::authorize('ronove.publish');
        }

        $locale = Locale::query()->where('code', $validated['locale'])->firstOrFail();
        abort_unless($locale->is_enabled && $locale->isTranslationTarget(), 404);
        $values = $this->validatedValues($provider, $validated);
        $userId = $request->user() === null
            ? null
            : (int) $request->user()->getAuthIdentifier();

        if ($reviewWorkflowEnabled && in_array($workflowAction, ['submit', 'publish'], true) && $values === []) {
            throw ValidationException::withMessages([
                'values' => trans('ronove::admin.reviews.empty_submission'),
            ]);
        }

        [$translation, $previousStatus] = DB::transaction(function () use (
            $provider,
            $model,
            $locale,
            $values,
            $resolver,
            $reviewWorkflowEnabled,
            $workflowAction,
            $publishFromReview,
            $status,
            $revisions,
            $userId,
        ) {
            $resource = Resource::query()->firstOrCreate([
                'resource_type' => $provider->type(),
                'resource_key' => $provider->key($model),
            ]);
            $existing = Translation::query()
                ->where('resource_id', $resource->id)
                ->where('locale_id', $locale->id)
                ->lockForUpdate()
                ->first();

            $reviewStatus = $reviewWorkflowEnabled
                ? match ($workflowAction) {
                    'submit' => Translation::REVIEW_PENDING,
                    'publish' => Translation::REVIEW_APPROVED,
                    default => Translation::REVIEW_DRAFT,
                }
            : ($status === Translation::PUBLISHED ? Translation::REVIEW_APPROVED : Translation::REVIEW_DRAFT);
            $sourceHash = $resolver->sourceHash($provider, $model);
            $publishedValues = $reviewWorkflowEnabled
                ? ($publishFromReview ? $values : $existing?->publicValues())
                : ($status === Translation::PUBLISHED ? $values : null);
            $publishedSourceHash = $reviewWorkflowEnabled
                ? ($publishFromReview ? $sourceHash : ($existing?->published_source_hash ?? ($existing?->isPublished() ? $existing->source_hash : null)))
                : ($status === Translation::PUBLISHED ? $sourceHash : null);
            $preserveFeedback = $reviewWorkflowEnabled
                && $workflowAction === 'save'
                && $existing?->review_feedback !== null
                && in_array($existing->review_status, [
                    Translation::REVIEW_DRAFT,
                    Translation::REVIEW_CHANGES_REQUESTED,
                ], true);

            $translation = Translation::query()->updateOrCreate(
                [
                    'resource_id' => $resource->id,
                    'locale_id' => $locale->id,
                ],
                [
                    'status' => $status,
                    'review_status' => $reviewStatus,
                    'values' => $values,
                    'source_hash' => $sourceHash,
                    'published_values' => $publishedValues,
                    'published_source_hash' => $publishedSourceHash,
                    'reviewed_by' => $preserveFeedback
                        ? $existing?->reviewed_by
                        : ($reviewStatus === Translation::REVIEW_APPROVED ? $userId : null),
                    'reviewed_at' => $preserveFeedback
                        ? $existing?->reviewed_at
                        : ($reviewStatus === Translation::REVIEW_APPROVED ? now() : null),
                    'review_feedback' => $preserveFeedback ? $existing?->review_feedback : null,
                ],
            );
            $revisions->record(
                $translation,
                match ($workflowAction) {
                    'submit' => TranslationRevision::SUBMITTED,
                    'publish' => TranslationRevision::APPROVED,
                    default => TranslationRevision::SAVED,
                },
                $userId,
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

        if ($reviewWorkflowEnabled && $workflowAction === 'submit') {
            TranslationSubmittedForReview::dispatch(
                $provider->type(),
                $provider->key($model),
                $locale->code,
                $userId,
            );
        }

        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ])->with('success', trans(match (true) {
            $reviewWorkflowEnabled && $workflowAction === 'submit' => 'ronove::admin.reviews.submitted',
            $status === Translation::PUBLISHED => 'ronove::admin.translations.published',
            default => 'ronove::admin.translations.updated',
        }));
    }

    public function destroy(
        ResourceRegistry $registry,
        string $type,
        string $key,
        Locale $locale,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        abort_unless($locale->is_enabled && $locale->isTranslationTarget(), 404);
        $resource = Resource::query()
            ->where('resource_type', $provider->type())
            ->where('resource_key', $provider->key($model))
            ->first();

        $translation = $resource?->translations()->where('locale_id', $locale->id)->first();
        $translation?->delete();

        if ($resource !== null
            && ! $resource->translations()->exists()
            && ! $resource->notes()->exists()) {
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

    public function updateNote(
        Request $request,
        ResourceRegistry $registry,
        string $type,
        string $key,
        Locale $locale,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        abort_unless($locale->is_enabled && $locale->isTranslationTarget(), 404);
        $validated = $request->validate([
            'note' => ['required', 'string', 'not_regex:/^\s*$/u', 'max:5000'],
        ]);
        $resource = Resource::query()->firstOrCreate([
            'resource_type' => $provider->type(),
            'resource_key' => $provider->key($model),
        ]);

        TranslationNote::query()->updateOrCreate(
            [
                'resource_id' => $resource->id,
                'locale_id' => $locale->id,
            ],
            ['note' => trim($validated['note'])],
        );

        ActionLog::log('ronove.notes.saved', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
        ]);

        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.translations.note_saved'));
    }

    public function destroyNote(
        ResourceRegistry $registry,
        string $type,
        string $key,
        Locale $locale,
    ) {
        $provider = $this->provider($registry, $type);
        $model = $this->model($provider, $key);
        abort_unless($locale->is_enabled && $locale->isTranslationTarget(), 404);
        $resource = Resource::query()
            ->where('resource_type', $provider->type())
            ->where('resource_key', $provider->key($model))
            ->first();
        $note = $resource?->notes()->where('locale_id', $locale->id)->first();
        $note?->delete();

        if ($resource !== null
            && ! $resource->translations()->exists()
            && ! $resource->notes()->exists()) {
            $resource->delete();
        }

        if ($note !== null) {
            ActionLog::log('ronove.notes.deleted', data: [
                'resource' => $provider->type().':'.$provider->key($model),
                'locale' => $locale->code,
            ]);
        }

        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.translations.note_deleted'));
    }

    private function provider(ResourceRegistry $registry, string $type): ResourceProvider
    {
        abort_unless($registry->has($type), 404);

        $provider = $registry->get($type);
        $this->authorizeIntegration($registry->integrationFor($type));
        $this->authorizeProvider($provider);

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function translationRules(
        ResourceProvider $provider,
        bool $reviewWorkflowEnabled = false,
        bool $requireWorkflowAction = false,
    ): array {
        $rules = [
            'locale' => [
                'required', 'string',
                Rule::exists('ronove_locales', 'code')->where(fn ($query) => $query
                    ->where('is_enabled', true)
                    ->where('code', '!=', Locale::globalCode())),
            ],
            'status' => [
                $reviewWorkflowEnabled ? 'nullable' : 'required',
                Rule::in([Translation::DRAFT, Translation::PUBLISHED]),
            ],
            'workflow_action' => [
                $reviewWorkflowEnabled && $requireWorkflowAction ? 'required' : 'nullable',
                Rule::in(['save', 'submit', 'publish']),
            ],
            'values' => ['nullable', 'array'],
        ];

        foreach ($provider->fields() as $field => $definition) {
            $rules['values.'.$field] = array_filter([
                'nullable',
                'string',
                $definition->maxLength === null ? null : 'max:'.$definition->maxLength,
            ]);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function validatedValues(ResourceProvider $provider, array $validated): array
    {
        return collect($validated['values'] ?? [])
            ->only(array_keys($provider->fields()))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->all();
    }

    /**
     * @return Collection<int, GlossaryTerm>
     */
    private function glossarySuggestions(
        TranslationIntegration $integration,
        ResourceProvider $provider,
        Model $model,
        Locale $locale,
    ): Collection {
        $source = collect(array_keys($provider->fields()))
            ->map(fn (string $field) => $provider->original($model, $field))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn (string $value) => html_entity_decode(strip_tags($value)))
            ->implode("\n");

        if ($source === '') {
            return collect();
        }

        return GlossaryTerm::query()
            ->where('locale_id', $locale->id)
            ->whereIn('scope', [GlossaryTerm::GLOBAL_SCOPE, $integration->id])
            ->get()
            ->filter(fn (GlossaryTerm $term) => mb_stripos($source, $term->source_text) !== false)
            ->sortBy(fn (GlossaryTerm $term) => [
                $term->scope === $integration->id ? 0 : 1,
                mb_strtolower($term->source_text),
            ])
            ->take(20)
            ->values();
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
