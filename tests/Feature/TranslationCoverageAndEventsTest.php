<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Post;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Events\LocaleChanged;
use Azuriom\Plugin\Ronove\Events\TranslationDeleted;
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Azuriom\Plugin\Ronove\Events\TranslationSaved;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Support\TranslationCoverageReport;
use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TranslationCoverageAndEventsTest extends TestCase
{
    public function test_coverage_reports_and_filters_missing_draft_published_and_outdated_resources(): void
    {
        $admin = $this->user(admin: true);
        $locale = $this->locale();
        $current = $this->createPost($admin, 'Current original', 'current-original');
        $stale = $this->createPost($admin, 'Stale original', 'stale-original');
        $missing = $this->createPost($admin, 'Untranslated original', 'untranslated-original');
        $provider = app(ResourceRegistry::class)->get('core.post');
        $resolver = app(TranslationResolver::class);

        $this->translation(
            $current,
            $locale,
            Translation::PUBLISHED,
            $resolver->sourceHash($provider, $current),
        );
        $this->translation($stale, $locale, Translation::DRAFT, str_repeat('0', 64));

        $coverage = app('ronove')->coverage('core.post', 'es_ES');

        $this->assertSame(3, $coverage->total());
        $this->assertSame(1, $coverage->count(TranslationCoverageReport::MISSING));
        $this->assertSame(1, $coverage->count(Translation::DRAFT));
        $this->assertSame(1, $coverage->count(Translation::PUBLISHED));
        $this->assertSame(1, $coverage->count(TranslationCoverageReport::OUTDATED));
        $this->assertTrue($coverage->keysFor(TranslationCoverageReport::MISSING)->contains((string) $missing->id));
        $this->assertTrue($coverage->keysFor(TranslationCoverageReport::OUTDATED)->contains((string) $stale->id));

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.integration', [
                'integration' => 'core',
                'type' => 'core.post',
                'locale' => 'es_ES',
                'status' => TranslationCoverageReport::OUTDATED,
            ]))
            ->assertOk()
            ->assertSee('Stale original')
            ->assertDontSee('Current original')
            ->assertDontSee('Untranslated original');

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.integration', [
                'integration' => 'core',
                'type' => 'core.post',
                'locale' => 'es_ES',
                'search' => 'Untranslated',
            ]))
            ->assertOk()
            ->assertSee('Untranslated original')
            ->assertDontSee('Current original')
            ->assertDontSee('Stale original');
    }

    public function test_the_public_api_can_overlay_a_collection_without_persisting_translated_values(): void
    {
        $author = $this->user();
        $locale = $this->locale();
        $post = $this->createPost($author, 'Original title', 'original-title');
        $this->translation($post, $locale, Translation::PUBLISHED, null, [
            'title' => 'Titulo publico',
            'content' => '<p>Contenido publico</p>',
        ]);

        app('ronove')->overlay('core.post', [$post], 'es_ES');

        $this->assertSame('Titulo publico', $post->title);
        $this->assertSame('<p>Contenido publico</p>', $post->content);
        $this->assertSame('Original title', $post->fresh()->title);
    }

    public function test_translation_and_locale_events_expose_stable_scalar_payloads(): void
    {
        Event::fake([
            LocaleChanged::class,
            TranslationDeleted::class,
            TranslationPublished::class,
            TranslationSaved::class,
        ]);

        $admin = $this->user(admin: true);
        $locale = $this->locale();
        $post = $this->createPost($admin, 'Event source', 'event-source');
        $payload = [
            'locale' => 'es_ES',
            'status' => Translation::PUBLISHED,
            'values' => [
                'title' => 'Evento traducido',
                'content' => '<p>Contenido del evento</p>',
            ],
        ];

        $this->actingAs($admin)
            ->put(route('ronove.admin.translations.update', [
                'type' => 'core.post',
                'key' => $post->id,
            ]), $payload)
            ->assertRedirect();

        Event::assertDispatched(TranslationSaved::class, fn (TranslationSaved $event) => $event->resourceType === 'core.post'
            && $event->resourceKey === (string) $post->id
            && $event->locale === 'es_ES'
            && $event->status === Translation::PUBLISHED
            && $event->previousStatus === null
            && $event->values === $payload['values']);
        Event::assertDispatched(TranslationPublished::class, fn (TranslationPublished $event) => $event->resourceType === 'core.post'
            && $event->resourceKey === (string) $post->id
            && $event->locale === 'es_ES'
            && $event->previousStatus === null);

        $this->actingAs($admin)
            ->put(route('ronove.admin.translations.update', [
                'type' => 'core.post',
                'key' => $post->id,
            ]), $payload)
            ->assertRedirect();

        Event::assertDispatchedTimes(TranslationSaved::class, 2);
        Event::assertDispatchedTimes(TranslationPublished::class, 1);

        $this->actingAs($admin)
            ->delete(route('ronove.admin.translations.destroy', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $locale,
            ]))
            ->assertRedirect();

        Event::assertDispatched(TranslationDeleted::class, fn (TranslationDeleted $event) => $event->resourceType === 'core.post'
            && $event->resourceKey === (string) $post->id
            && $event->locale === 'es_ES'
            && $event->previousStatus === Translation::PUBLISHED);

        $this->from('/ronove')->post('/ronove/locale', ['locale' => 'es_ES'])->assertRedirect('/ronove');

        Event::assertDispatched(LocaleChanged::class, fn (LocaleChanged $event) => $event->locale === 'es_ES' && $event->userId === $admin->id);
    }

    private function user(bool $admin = false): User
    {
        $user = User::query()->create([
            'name' => $admin ? 'Ronove Admin' : 'Ronove Author',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => 1,
        ]);

        if ($admin) {
            $user->role->forceFill(['is_admin' => true])->save();
        }

        return $user;
    }

    private function locale(): Locale
    {
        return Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Espanol',
            'is_enabled' => true,
            'position' => 0,
        ]);
    }

    private function createPost(User $author, string $title, string $slug): Post
    {
        return $author->posts()->create([
            'title' => $title,
            'description' => 'Original SEO description',
            'slug' => $slug,
            'content' => '<p>Original content</p>',
            'is_pinned' => false,
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function translation(
        Post $post,
        Locale $locale,
        string $status,
        ?string $sourceHash,
        array $values = ['title' => 'Translated title'],
    ): Translation {
        $resource = Resource::query()->create([
            'resource_type' => 'core.post',
            'resource_key' => (string) $post->id,
        ]);

        return Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $locale->id,
            'status' => $status,
            'values' => $values,
            'source_hash' => $sourceHash,
        ]);
    }
}
