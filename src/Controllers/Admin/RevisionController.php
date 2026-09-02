<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Events\TranslationSaved;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Models\TranslationRevision;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationRevisionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevisionController extends Controller
{
    public function restore(
        Request $request,
        ResourceRegistry $registry,
        TranslationRevisionRecorder $revisions,
        string $type,
        string $key,
        Locale $locale,
        TranslationRevision $revision,
    ) {
        abort_unless($locale->is_enabled && $locale->isTranslationTarget() && $registry->has($type), 404);
        $provider = $registry->get($type);
        $integration = $registry->integrationFor($type);

        if ($integration->permission !== null) {
            Gate::authorize($integration->permission);
        }

        if ($provider->permission() !== null) {
            Gate::authorize($provider->permission());
        }

        $model = $provider->find($key) ?? abort(404);
        $resource = Resource::query()
            ->where('resource_type', $provider->type())
            ->where('resource_key', $provider->key($model))
            ->firstOrFail();
        $translation = $resource->translations()
            ->where('locale_id', $locale->id)
            ->firstOrFail();
        abort_unless($revision->translation_id === $translation->id, 404);
        $userId = $request->user() === null
            ? null
            : (int) $request->user()->getAuthIdentifier();
        $values = $this->validValues($provider, $revision->values);

        [$translation, $previousStatus] = DB::transaction(function () use (
            $translation,
            $revision,
            $revisions,
            $values,
            $userId,
        ) {
            $translation = Translation::query()->lockForUpdate()->findOrFail($translation->id);
            $previousStatus = $translation->status;
            $translation->update([
                'status' => Translation::DRAFT,
                'review_status' => Translation::REVIEW_DRAFT,
                'values' => $values,
                'source_hash' => $revision->source_hash,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_feedback' => null,
            ]);
            $revisions->record(
                $translation,
                TranslationRevision::RESTORED,
                $userId,
                'revision:'.$revision->id,
            );

            return [$translation, $previousStatus];
        });

        ActionLog::log('ronove.revisions.restored', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
            'revision' => $revision->id,
        ]);
        TranslationSaved::dispatch(
            $provider->type(),
            $provider->key($model),
            $locale->code,
            $translation->status,
            $translation->values,
            $previousStatus,
        );

        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ])->with('success', trans('ronove::admin.revisions.restored'));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function validValues(ResourceProvider $provider, array $values): array
    {
        return collect($values)
            ->only(array_keys($provider->fields()))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->all();
    }
}
