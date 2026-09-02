<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Post;
use Azuriom\Models\Role;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Events\TranslationDeleted;
use Azuriom\Plugin\Ronove\Models\GlossaryTerm;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Models\TranslationNote;
use Azuriom\Plugin\Ronove\Models\UserPreference;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Support\TranslationAuditIssue;
use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class AuditAndCleanupTest extends TestCase
{
    public function test_a_consistent_database_has_a_healthy_audit_report(): void
    {
        $admin = $this->user(admin: true);
        $limitedRole = Role::query()->create([
            'name' => 'No Ronove Audit',
            'color' => '6c757d',
            'power' => 1,
            'is_admin' => false,
        ]);
        $user = User::query()->forceCreate([
            'name' => 'Limited User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $limitedRole->id,
        ]);
        $this->locale('en', true);

        $this->assertFalse($user->fresh()->isAdmin());
        $this->assertFalse($user->fresh()->can('ronove.audit'));
        $this->assertContains(
            'can:ronove.audit',
            app('router')->getRoutes()->getByName('ronove.admin.audit.index')->gatherMiddleware(),
        );

        $this->actingAs($user)
            ->get(route('ronove.admin.audit.index'))
            ->assertForbidden();

        $this->flushSession();
        $this->actingAs($admin)
            ->get(route('ronove.admin.audit.index'))
            ->assertOk()
            ->assertSee('No audit findings were detected.')
            ->assertSee('Opening or refreshing this page never changes data.');
    }

    public function test_the_audit_is_read_only_and_outdated_translations_require_manual_review(): void
    {
        $admin = $this->user(admin: true);
        $this->locale('en', true);
        $locale = $this->locale('es_ES', true);
        $post = $this->createPost($admin, 'Changed source', 'changed-source');
        $translation = $this->translation($post, $locale, [
            'title' => 'Fuente anterior',
        ], str_repeat('0', 64));

        $this->actingAs($admin)
            ->get(route('ronove.admin.audit.index'))
            ->assertOk()
            ->assertSee('Audit and cleanup')
            ->assertSee('Outdated translations')
            ->assertSee('core.post:'.$post->id)
            ->assertSee('Manual review required');

        $this->assertDatabaseHas('ronove_translations', [
            'id' => $translation->id,
            'source_hash' => str_repeat('0', 64),
        ]);

        $this->actingAs($admin)
            ->post(route('ronove.admin.audit.cleanup', TranslationAuditIssue::OUTDATED_TRANSLATIONS), [
                'confirm' => '1',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('ronove_translations', ['id' => $translation->id]);
    }

    public function test_structural_findings_are_cleaned_independently_without_touching_valid_values(): void
    {
        Event::fake([TranslationDeleted::class]);
        $admin = $this->user(admin: true);
        $this->locale('en', true);
        $locale = $this->locale('es_ES', true);
        $resolver = app(TranslationResolver::class);
        $provider = app(ResourceRegistry::class)->get('core.post');

        $emptyResource = Resource::query()->create([
            'resource_type' => 'core.post',
            'resource_key' => '9991',
        ]);

        $unknownResource = Resource::query()->create([
            'resource_type' => 'removed.integration',
            'resource_key' => 'external-1',
        ]);
        $unknownTranslation = Translation::query()->create([
            'resource_id' => $unknownResource->id,
            'locale_id' => $locale->id,
            'status' => Translation::PUBLISHED,
            'values' => ['content' => 'Preserved until explicit cleanup'],
            'source_hash' => str_repeat('1', 64),
        ]);

        $missingSource = Resource::query()->create([
            'resource_type' => 'core.post',
            'resource_key' => '9992',
        ]);
        TranslationNote::query()->create([
            'resource_id' => $missingSource->id,
            'locale_id' => $locale->id,
            'note' => 'Orphaned note',
        ]);

        $emptyPost = $this->createPost($admin, 'Empty translation', 'empty-translation');
        $emptyTranslation = $this->translation($emptyPost, $locale, []);

        $invalidPost = $this->createPost($admin, 'Invalid fields', 'invalid-fields');
        $invalidTranslation = $this->translation(
            $invalidPost,
            $locale,
            ['title' => 'Título válido', 'legacy' => 'Obsoleto', 'content' => '   '],
            $resolver->sourceHash($provider, $invalidPost),
        );

        $statusPost = $this->createPost($admin, 'Invalid status', 'invalid-status');
        $statusTranslation = $this->translation(
            $statusPost,
            $locale,
            ['title' => 'Estado inválido'],
            $resolver->sourceHash($provider, $statusPost),
            'archived',
        );
        $blankNote = TranslationNote::query()->create([
            'resource_id' => $statusTranslation->resource_id,
            'locale_id' => $locale->id,
            'note' => '   ',
        ]);

        $response = $this->actingAs($admin)->get(route('ronove.admin.audit.index'));

        $response->assertOk()
            ->assertSee('Empty Ronove resources')
            ->assertSee('Unavailable resource providers')
            ->assertSee('Missing original resources')
            ->assertSee('Empty translations')
            ->assertSee('Invalid translation fields')
            ->assertSee('Invalid translation statuses')
            ->assertSee('Blank internal notes');

        $this->actingAs($admin)
            ->post(route('ronove.admin.audit.cleanup', TranslationAuditIssue::EMPTY_RESOURCES))
            ->assertSessionHasErrors('confirm');
        $this->assertDatabaseHas('ronove_resources', ['id' => $emptyResource->id]);

        $this->cleanup($admin, TranslationAuditIssue::EMPTY_RESOURCES);
        $this->assertDatabaseMissing('ronove_resources', ['id' => $emptyResource->id]);
        $this->assertDatabaseHas('ronove_resources', ['id' => $unknownResource->id]);

        $this->cleanup($admin, TranslationAuditIssue::MISSING_PROVIDERS);
        $this->assertDatabaseMissing('ronove_resources', ['id' => $unknownResource->id]);
        Event::assertDispatched(TranslationDeleted::class, fn (TranslationDeleted $event) => $event->resourceType === 'removed.integration'
            && $event->resourceKey === 'external-1'
            && $event->locale === 'es_ES'
            && $event->previousStatus === Translation::PUBLISHED);

        $this->cleanup($admin, TranslationAuditIssue::MISSING_SOURCES);
        $this->assertDatabaseMissing('ronove_resources', ['id' => $missingSource->id]);

        $this->cleanup($admin, TranslationAuditIssue::EMPTY_TRANSLATIONS);
        $this->assertDatabaseMissing('ronove_translations', ['id' => $emptyTranslation->id]);
        $this->assertDatabaseMissing('ronove_resources', ['id' => $emptyTranslation->resource_id]);

        $this->cleanup($admin, TranslationAuditIssue::INVALID_TRANSLATION_VALUES);
        $this->assertSame(
            ['title' => 'Título válido'],
            $invalidTranslation->fresh()->values,
        );

        $this->cleanup($admin, TranslationAuditIssue::INVALID_TRANSLATION_STATUSES);
        $this->assertSame(Translation::DRAFT, $statusTranslation->fresh()->status);

        $this->cleanup($admin, TranslationAuditIssue::BLANK_NOTES);
        $this->assertDatabaseMissing('ronove_translation_notes', ['id' => $blankNote->id]);
        $this->assertDatabaseHas('ronove_translations', ['id' => $statusTranslation->id]);
        $this->assertDatabaseHas('ronove_translations', ['id' => $invalidTranslation->id]);
        $this->assertDatabaseMissing('ronove_translations', ['id' => $unknownTranslation->id]);
    }

    public function test_language_preferences_fallbacks_and_glossary_scopes_can_be_cleaned(): void
    {
        $admin = $this->user(admin: true);
        $user = $this->user();
        $english = $this->locale('en', true);
        $spanish = $this->locale('es_ES', false);
        $french = $this->locale('fr', true);
        $german = $this->locale('de', true);

        $spanish->update(['fallback_locale_id' => $english->id]);
        $french->update(['fallback_locale_id' => $german->id]);
        $german->update(['fallback_locale_id' => $french->id]);
        UserPreference::query()->create([
            'user_id' => $user->id,
            'locale_id' => $spanish->id,
        ]);
        $term = GlossaryTerm::query()->create([
            'scope' => 'removed.integration',
            'locale_id' => $english->id,
            'source_text' => 'Old integration term',
            'translated_text' => 'Old integration translation',
        ]);

        $this->actingAs($admin)
            ->get(route('ronove.admin.audit.index'))
            ->assertOk()
            ->assertSee('Unavailable glossary scopes')
            ->assertSee('Preferences for disabled languages')
            ->assertSee('Invalid regional fallbacks')
            ->assertSee('Old integration term')
            ->assertSee('The regional fallback chain contains a cycle.');

        $this->cleanup($admin, TranslationAuditIssue::UNKNOWN_GLOSSARY_SCOPES);
        $this->cleanup($admin, TranslationAuditIssue::DISABLED_LOCALE_PREFERENCES);
        $this->cleanup($admin, TranslationAuditIssue::INVALID_FALLBACKS);

        $this->assertDatabaseMissing('ronove_glossary_terms', ['id' => $term->id]);
        $this->assertDatabaseCount('ronove_user_preferences', 0);
        $this->assertNull($spanish->fresh()->fallback_locale_id);
        $this->assertNull($french->fresh()->fallback_locale_id);
        $this->assertNull($german->fresh()->fallback_locale_id);
    }

    private function cleanup(User $admin, string $category): void
    {
        $this->actingAs($admin)
            ->post(route('ronove.admin.audit.cleanup', $category), ['confirm' => '1'])
            ->assertRedirect(route('ronove.admin.audit.index'))
            ->assertSessionHasNoErrors();
    }

    private function locale(string $code, bool $enabled): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => $code,
            'native_name' => $code,
            'is_enabled' => $enabled,
            'position' => Locale::query()->count(),
        ]);
    }

    private function user(bool $admin = false): User
    {
        $user = User::query()->create([
            'name' => $admin ? 'Ronove Admin' : fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => 1,
        ]);

        if ($admin) {
            $user->role->forceFill(['is_admin' => true])->save();
        }

        return $user;
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
     * @param  array<string, mixed>  $values
     */
    private function translation(
        Post $post,
        Locale $locale,
        array $values,
        ?string $sourceHash = null,
        string $status = Translation::DRAFT,
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
