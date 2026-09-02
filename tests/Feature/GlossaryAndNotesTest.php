<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Post;
use Azuriom\Models\Setting;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Models\GlossaryTerm;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Support\TranslationCoverageReport;
use Azuriom\Plugin\Ronove\Tests\TestCase;

class GlossaryAndNotesTest extends TestCase
{
    public function test_glossary_index_uses_a_table_and_separate_create_and_edit_pages(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $spanish = $this->locale('es_ES', 'Español');
        $term = GlossaryTerm::query()->create([
            'scope' => GlossaryTerm::GLOBAL_SCOPE,
            'locale_id' => $spanish->id,
            'source_text' => 'Server',
            'translated_text' => 'Servidor',
            'context' => 'Infrastructure terminology.',
        ]);

        $indexUrl = route('ronove.admin.glossary.index', [
            'scope' => GlossaryTerm::GLOBAL_SCOPE,
            'locale' => $spanish->code,
        ]);

        $this->actingAs($admin)
            ->get($indexUrl)
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSee('Server')
            ->assertSee('Servidor')
            ->assertSee(route('ronove.admin.glossary.create', [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
            ]))
            ->assertSee(route('ronove.admin.glossary.edit', $term), false)
            ->assertDontSee('name="source_text"', false)
            ->assertDontSee('name="translated_text"', false);

        $this->actingAs($admin)
            ->get(route('ronove.admin.glossary.create', [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
            ]))
            ->assertOk()
            ->assertSee('Add glossary term')
            ->assertSee('name="source_text"', false)
            ->assertSee('value="es_ES" selected', false);

        $this->actingAs($admin)
            ->get(route('ronove.admin.glossary.edit', $term))
            ->assertOk()
            ->assertSee('Edit glossary term')
            ->assertSee('value="Server"', false)
            ->assertSee('value="Servidor"', false);
    }

    public function test_glossary_terms_are_scoped_searchable_and_suggested_without_translating_content(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $this->locale('en', 'English');
        $spanish = $this->locale('es_ES', 'Español');
        $post = $this->createPost($admin, 'Server status', 'server-status');

        $this->actingAs($admin)
            ->post(route('ronove.admin.glossary.store'), [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
                'source_text' => 'Server',
                'translated_text' => 'Servidor',
                'context' => 'Use for the multiplayer server, not a waiter.',
            ])
            ->assertRedirect(route('ronove.admin.glossary.index', [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
            ]))
            ->assertSessionHasNoErrors();

        $term = GlossaryTerm::query()->firstOrFail();
        $this->assertSame(GlossaryTerm::keyFor('server'), $term->source_key);

        $this->actingAs($admin)
            ->from(route('ronove.admin.glossary.index'))
            ->post(route('ronove.admin.glossary.store'), [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
                'source_text' => 'server',
                'translated_text' => 'Otro servidor',
            ])
            ->assertRedirect(route('ronove.admin.glossary.index'))
            ->assertSessionHasErrors('source_text');

        $this->assertDatabaseCount('ronove_glossary_terms', 1);

        $this->actingAs($admin)
            ->post(route('ronove.admin.glossary.store'), [
                'scope' => 'core',
                'locale' => $spanish->code,
                'source_text' => 'Original content',
                'translated_text' => 'Contenido original',
                'context' => 'Only for Azuriom content.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ronove_glossary_terms', 2);

        $this->actingAs($admin)
            ->get(route('ronove.admin.glossary.index', [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
                'search' => 'multiplayer',
            ]))
            ->assertOk()
            ->assertSee('Server')
            ->assertSee('Servidor')
            ->assertSee('Use for the multiplayer server');

        $this->actingAs($admin)
            ->put(route('ronove.admin.glossary.update', $term), [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
                'source_text' => 'Server status',
                'translated_text' => 'Estado del servidor',
                'context' => 'Preferred status-page terminology.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.edit', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish->code,
            ]))
            ->assertOk()
            ->assertSee('Glossary suggestions')
            ->assertSee('Server status')
            ->assertSee('Estado del servidor')
            ->assertSee('Preferred status-page terminology.')
            ->assertSee('Contenido original')
            ->assertSee('Only for Azuriom content.');

        $integrationTerm = GlossaryTerm::query()->where('scope', 'core')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('ronove.admin.glossary.destroy', $integrationTerm))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('ronove_glossary_terms', ['id' => $integrationTerm->id]);

        $this->assertSame(
            'Server status',
            app(TranslationResolver::class)->translate('core.post', $post, 'title', 'es_ES'),
        );
    }

    public function test_notes_are_private_and_independent_from_translation_status(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $this->locale('en', 'English');
        $spanish = $this->locale('es_ES', 'Español');
        $post = $this->createPost($admin, 'Note source', 'note-source');
        $noteText = 'Keep this explanation private for translators.';

        $this->actingAs($admin)
            ->put(route('ronove.admin.translations.notes.update', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]), ['note' => $noteText])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $resource = Resource::query()->firstOrFail();
        $this->assertDatabaseCount('ronove_translation_notes', 1);
        $this->assertDatabaseCount('ronove_translations', 0);
        $this->assertSame(
            1,
            app('ronove')->coverage('core.post', 'es_ES')->count(TranslationCoverageReport::MISSING),
        );

        $updatedNoteText = 'Updated private explanation for the translation team.';

        $this->actingAs($admin)
            ->put(route('ronove.admin.translations.notes.update', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]), ['note' => $updatedNoteText])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ronove_translation_notes', 1);
        $this->assertDatabaseHas('ronove_translation_notes', [
            'resource_id' => $resource->id,
            'locale_id' => $spanish->id,
            'note' => $updatedNoteText,
        ]);

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.edit', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish->code,
            ]))
            ->assertOk()
            ->assertSee($updatedNoteText);

        $this->withSession([LocaleManager::SESSION_KEY => $spanish->code])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertDontSee($updatedNoteText);

        Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $spanish->id,
            'status' => Translation::DRAFT,
            'values' => ['title' => 'Borrador'],
        ]);

        $this->actingAs($admin)
            ->delete(route('ronove.admin.translations.destroy', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('ronove_resources', ['id' => $resource->id]);
        $this->assertDatabaseHas('ronove_translation_notes', ['resource_id' => $resource->id]);

        $this->actingAs($admin)
            ->delete(route('ronove.admin.translations.notes.destroy', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('ronove_translation_notes', ['resource_id' => $resource->id]);
        $this->assertDatabaseMissing('ronove_resources', ['id' => $resource->id]);
    }

    private function locale(string $code, string $name): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => $name,
            'native_name' => $name,
            'is_enabled' => true,
            'position' => Locale::query()->count(),
        ]);
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
}
