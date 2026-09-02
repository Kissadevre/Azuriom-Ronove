<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Models\GlossaryTerm;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GlossaryController extends Controller
{
    public function index(Request $request, ResourceRegistry $registry)
    {
        $scopes = $this->accessibleScopes($registry);
        $locales = Locale::query()
            ->where('is_enabled', true)
            ->translationTargets()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $validated = $request->validate([
            'scope' => ['nullable', 'string', Rule::in($scopes->keys())],
            'locale' => [
                'nullable', 'string',
                Rule::exists('ronove_locales', 'code')->where(fn ($query) => $query
                    ->where('is_enabled', true)
                    ->where('code', '!=', Locale::globalCode())),
            ],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $scope = $validated['scope'] ?? GlossaryTerm::GLOBAL_SCOPE;
        $locale = isset($validated['locale'])
            ? $locales->firstWhere('code', $validated['locale'])
            : $locales->first();
        $search = trim($validated['search'] ?? '');
        $query = GlossaryTerm::query()
            ->with('locale')
            ->where('scope', $scope)
            ->when(
                $locale === null,
                fn ($query) => $query->whereRaw('1 = 0'),
                fn ($query) => $query->where('locale_id', $locale->id),
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('source_text', 'like', '%'.$search.'%')
                        ->orWhere('translated_text', 'like', '%'.$search.'%')
                        ->orWhere('context', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('source_text');

        return view('ronove::admin.glossary.index', [
            'scopes' => $scopes,
            'locales' => $locales,
            'selectedScope' => $scope,
            'selectedLocale' => $locale,
            'search' => $search,
            'terms' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request, ResourceRegistry $registry)
    {
        $validated = $this->validatedTerm($request, $registry);
        $locale = Locale::query()->where('code', $validated['locale'])->firstOrFail();
        $this->ensureUnique($validated['scope'], $locale->id, $validated['source_text']);

        GlossaryTerm::query()->create([
            'scope' => $validated['scope'],
            'locale_id' => $locale->id,
            'source_text' => $validated['source_text'],
            'translated_text' => trim($validated['translated_text']),
            'context' => $this->optionalText($validated['context'] ?? null),
        ]);

        ActionLog::log('ronove.glossary.saved');

        return to_route('ronove.admin.glossary.index', [
            'scope' => $validated['scope'],
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.glossary.saved'));
    }

    public function update(
        Request $request,
        ResourceRegistry $registry,
        GlossaryTerm $term,
    ) {
        $this->authorizeTerm($registry, $term);
        $request->merge(['editing_term' => (string) $term->id]);
        $validated = $this->validatedTerm($request, $registry);
        $locale = Locale::query()->where('code', $validated['locale'])->firstOrFail();
        $this->ensureUnique($validated['scope'], $locale->id, $validated['source_text'], $term);

        $term->update([
            'scope' => $validated['scope'],
            'locale_id' => $locale->id,
            'source_text' => $validated['source_text'],
            'translated_text' => trim($validated['translated_text']),
            'context' => $this->optionalText($validated['context'] ?? null),
        ]);

        ActionLog::log('ronove.glossary.saved');

        return to_route('ronove.admin.glossary.index', [
            'scope' => $validated['scope'],
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.glossary.saved'));
    }

    public function destroy(ResourceRegistry $registry, GlossaryTerm $term)
    {
        $this->authorizeTerm($registry, $term);
        $scope = $term->scope;
        $locale = $term->locale?->code;
        $term->delete();

        ActionLog::log('ronove.glossary.deleted');

        return to_route('ronove.admin.glossary.index', array_filter([
            'scope' => $scope,
            'locale' => $locale,
        ]))->with('success', trans('ronove::admin.glossary.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTerm(Request $request, ResourceRegistry $registry): array
    {
        return $request->validate([
            'scope' => ['required', 'string', 'max:80', Rule::in($this->accessibleScopes($registry)->keys())],
            'locale' => [
                'required', 'string',
                Rule::exists('ronove_locales', 'code')->where(fn ($query) => $query
                    ->where('is_enabled', true)
                    ->where('code', '!=', Locale::globalCode())),
            ],
            'source_text' => ['required', 'string', 'not_regex:/^\s*$/u', 'max:100'],
            'translated_text' => ['required', 'string', 'not_regex:/^\s*$/u', 'max:191'],
            'context' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function ensureUnique(
        string $scope,
        int $localeId,
        string $source,
        ?GlossaryTerm $ignore = null,
    ): void {
        $query = GlossaryTerm::query()
            ->where('scope', $scope)
            ->where('locale_id', $localeId)
            ->where('source_key', GlossaryTerm::keyFor($source));

        if ($ignore !== null) {
            $query->whereKeyNot($ignore->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'source_text' => trans('ronove::admin.glossary.duplicate'),
            ]);
        }
    }

    private function authorizeTerm(ResourceRegistry $registry, GlossaryTerm $term): void
    {
        abort_unless($this->accessibleScopes($registry)->has($term->scope), 403);
    }

    /**
     * @return Collection<string, string>
     */
    private function accessibleScopes(ResourceRegistry $registry): Collection
    {
        $scopes = collect([
            GlossaryTerm::GLOBAL_SCOPE => trans('ronove::admin.glossary.global_scope'),
        ]);

        return $registry->integrations()
            ->filter(fn (TranslationIntegration $integration) => $integration->permission === null
                || Gate::allows($integration->permission))
            ->filter(fn (TranslationIntegration $integration) => $registry->forIntegration($integration->id)
                ->contains(fn (ResourceProvider $provider) => $provider->permission() === null
                    || Gate::allows($provider->permission())))
            ->reduce(
                fn (Collection $scopes, TranslationIntegration $integration) => $scopes->put(
                    $integration->id,
                    $integration->label(),
                ),
                $scopes,
            );
    }

    private function optionalText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
