<?php

namespace Azuriom\Plugin\Ronove\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\ActionLog;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Azuriom\Plugin\Ronove\Events\TranslationReviewCompleted;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Models\TranslationRevision;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\ReviewWorkflow;
use Azuriom\Plugin\Ronove\Services\TranslationRevisionRecorder;
use Azuriom\Plugin\Ronove\Support\TranslationIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function approve(
        Request $request,
        ResourceRegistry $registry,
        ReviewWorkflow $workflow,
        TranslationRevisionRecorder $revisions,
        string $type,
        string $key,
        Locale $locale,
    ) {
        abort_unless($workflow->enabled(), 404);
        Gate::authorize('ronove.publish');
        [$provider, $model, $translation] = $this->translation($registry, $type, $key, $locale);
        $validated = $request->validate([
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
        $feedback = $this->optionalText($validated['feedback'] ?? null);
        $userId = $request->user() === null
            ? null
            : (int) $request->user()->getAuthIdentifier();

        [$translation, $previousStatus] = DB::transaction(function () use ($translation, $revisions, $feedback, $userId) {
            $translation = Translation::query()->lockForUpdate()->findOrFail($translation->id);
            abort_unless($translation->review_status === Translation::REVIEW_PENDING, 409);
            $previousStatus = $translation->hasPublishedVersion()
                ? Translation::PUBLISHED
                : $translation->status;
            $translation->update([
                'status' => Translation::PUBLISHED,
                'review_status' => Translation::REVIEW_APPROVED,
                'published_values' => $translation->values,
                'published_source_hash' => $translation->source_hash,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'review_feedback' => $feedback,
            ]);
            $revisions->record(
                $translation,
                TranslationRevision::APPROVED,
                $userId,
                $feedback,
            );

            return [$translation, $previousStatus];
        });

        ActionLog::log('ronove.reviews.approved', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
        ]);
        TranslationReviewCompleted::dispatch(
            $provider->type(),
            $provider->key($model),
            $locale->code,
            Translation::REVIEW_APPROVED,
            $feedback,
            $userId,
        );

        TranslationPublished::dispatch(
            $provider->type(),
            $provider->key($model),
            $locale->code,
            $translation->values,
            $previousStatus,
        );

        return $this->redirectToEditor($provider, $model, $locale)
            ->with('success', trans('ronove::admin.reviews.approved'));
    }

    public function requestChanges(
        Request $request,
        ResourceRegistry $registry,
        ReviewWorkflow $workflow,
        TranslationRevisionRecorder $revisions,
        string $type,
        string $key,
        Locale $locale,
    ) {
        abort_unless($workflow->enabled(), 404);
        [$provider, $model, $translation] = $this->translation($registry, $type, $key, $locale);
        $validated = $request->validate([
            'feedback' => ['required', 'string', 'not_regex:/^\s*$/u', 'max:5000'],
        ]);
        $feedback = trim($validated['feedback']);
        $userId = $request->user() === null
            ? null
            : (int) $request->user()->getAuthIdentifier();

        DB::transaction(function () use ($translation, $revisions, $feedback, $userId) {
            $translation = Translation::query()->lockForUpdate()->findOrFail($translation->id);
            abort_unless($translation->review_status === Translation::REVIEW_PENDING, 409);
            $translation->update([
                'status' => Translation::DRAFT,
                'review_status' => Translation::REVIEW_CHANGES_REQUESTED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'review_feedback' => $feedback,
            ]);
            $revisions->record(
                $translation,
                TranslationRevision::CHANGES_REQUESTED,
                $userId,
                $feedback,
            );
        });

        ActionLog::log('ronove.reviews.changes_requested', data: [
            'resource' => $provider->type().':'.$provider->key($model),
            'locale' => $locale->code,
        ]);
        TranslationReviewCompleted::dispatch(
            $provider->type(),
            $provider->key($model),
            $locale->code,
            Translation::REVIEW_CHANGES_REQUESTED,
            $feedback,
            $userId,
        );

        return $this->redirectToEditor($provider, $model, $locale)
            ->with('success', trans('ronove::admin.reviews.changes_requested'));
    }

    /**
     * @return array{ResourceProvider, Model, Translation}
     */
    private function translation(
        ResourceRegistry $registry,
        string $type,
        string $key,
        Locale $locale,
    ): array {
        abort_unless($locale->is_enabled && $registry->has($type), 404);
        $provider = $registry->get($type);
        $this->authorizeIntegration($registry->integrationFor($type));

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

        return [$provider, $model, $translation];
    }

    private function authorizeIntegration(TranslationIntegration $integration): void
    {
        if ($integration->permission !== null) {
            Gate::authorize($integration->permission);
        }
    }

    private function redirectToEditor(ResourceProvider $provider, Model $model, Locale $locale)
    {
        return to_route('ronove.admin.translations.edit', [
            'type' => $provider->type(),
            'key' => $provider->key($model),
            'locale' => $locale->code,
        ]);
    }

    private function optionalText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
