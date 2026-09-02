<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Post;
use Azuriom\Models\Setting;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Azuriom\Plugin\Ronove\Events\TranslationSaved;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class RegionalFallbackAndPreviewTest extends TestCase
{
    public function test_visible_fields_resolve_through_the_configured_regional_chain(): void
    {
        Setting::updateSettings('locale', 'en');
        $english = $this->locale('en', 'English');
        $spanish = $this->locale('es_ES', 'Español');
        $mexican = $this->locale('es_MX', 'Español (México)', $spanish);
        $author = $this->user();
        $regionalPost = $this->createPost($author, 'Original regional', 'original-regional');
        $globalPost = $this->createPost($author, 'Original global', 'original-global');

        $this->publish($regionalPost, $mexican, ['title' => 'Título mexicano']);
        $this->publish($regionalPost, $spanish, ['content' => '<p>Contenido regional</p>']);
        $this->publish($regionalPost, $english, ['content' => '<p>Global content</p>']);
        $this->publish($globalPost, $spanish, ['content' => '<p>Regional draft content</p>'])
            ->update(['status' => Translation::DRAFT]);
        $this->publish($globalPost, $english, ['content' => '<p>Global fallback content</p>']);

        $resolver = app(TranslationResolver::class);

        $this->assertSame(['es_MX', 'es_ES', 'en'], app(LocaleManager::class)->translationChain('es_MX'));
        $this->assertSame([
            'title' => 'Título mexicano',
            'content' => '<p>Contenido regional</p>',
        ], $resolver->values('core.post', $regionalPost, 'es_MX'));
        $this->assertSame([
            'title' => 'Original global',
            'content' => '<p>Original content</p>',
        ], $resolver->values('core.post', $globalPost, 'es_MX'));
    }

    public function test_the_framework_translator_uses_the_regional_chain_for_plugin_language_files(): void
    {
        Setting::updateSettings('locale', 'en');
        $spanish = $this->locale('es_ES', 'Español');
        $this->locale('es_MX', 'Español (México)', $spanish);

        $this->withSession([LocaleManager::SESSION_KEY => 'es_MX'])
            ->get('/ronove')
            ->assertOk()
            ->assertSee('<h1>Idioma</h1>', false)
            ->assertSee('Elige el idioma utilizado por Azuriom');

        $this->assertSame('es_MX', app()->getLocale());
    }

    public function test_administrators_can_save_fallbacks_but_cycles_are_rejected(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $english = $this->locale('en', 'English');
        $spanish = $this->locale('es_ES', 'Español');
        $mexican = $this->locale('es_MX', 'Español (México)');

        $this->actingAs($admin)
            ->post(route('ronove.admin.languages.fallbacks.update'), [
                'fallbacks' => [
                    'es_ES' => null,
                    'es_MX' => 'es_ES',
                ],
            ])
            ->assertRedirect(route('ronove.admin.languages.index'))
            ->assertSessionHasNoErrors();

        $this->assertNull($spanish->fresh()->fallback_locale_id);
        $this->assertSame($spanish->id, $mexican->fresh()->fallback_locale_id);

        $this->actingAs($admin)
            ->get(route('ronove.admin.languages.index'))
            ->assertOk()
            ->assertSee('Regional fallbacks')
            ->assertSee('Fallback for Español (México)');

        $this->actingAs($admin)
            ->from(route('ronove.admin.languages.index'))
            ->post(route('ronove.admin.languages.fallbacks.update'), [
                'fallbacks' => [
                    'es_ES' => 'es_MX',
                    'es_MX' => 'es_ES',
                ],
            ])
            ->assertRedirect(route('ronove.admin.languages.index'))
            ->assertSessionHasErrors('fallbacks.es_ES');

        $this->assertNull($spanish->fresh()->fallback_locale_id);
        $this->assertSame($spanish->id, $mexican->fresh()->fallback_locale_id);
    }

    public function test_preview_compares_unsaved_values_and_fallbacks_without_persisting_them(): void
    {
        Event::fake([TranslationPublished::class, TranslationSaved::class]);
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $this->locale('en', 'English');
        $spanish = $this->locale('es_ES', 'Spanish');
        $mexican = $this->locale('es_MX', 'Mexican Spanish', $spanish);
        $post = $this->createPost($admin, 'Original preview title', 'original-preview-title');
        $this->publish($post, $spanish, ['content' => '<p>Regional preview content</p>']);

        $this->actingAs($admin)
            ->put(route('ronove.admin.translations.preview', [
                'type' => 'core.post',
                'key' => $post->id,
            ]), [
                'locale' => $mexican->code,
                'status' => Translation::DRAFT,
                'values' => [
                    'title' => 'Unsaved preview title',
                    'content' => '',
                ],
            ])
            ->assertOk()
            ->assertSee('Preview and comparison')
            ->assertSee('Nothing was saved or published')
            ->assertSee('Original preview title')
            ->assertSee('Unsaved preview title')
            ->assertSee('Regional preview content')
            ->assertSee('Selected: Mexican Spanish')
            ->assertSee('Regional fallback: Spanish');

        $this->assertDatabaseCount('ronove_translations', 1);
        $this->assertDatabaseMissing('ronove_translations', ['locale_id' => $mexican->id]);
        $this->assertDatabaseCount('action_logs', 0);
        Event::assertNotDispatched(TranslationSaved::class);
        Event::assertNotDispatched(TranslationPublished::class);
    }

    public function test_disabling_a_language_clears_fallbacks_that_reference_it(): void
    {
        $admin = $this->user(admin: true);
        $english = $this->locale('en', 'English');
        $spanish = $this->locale('es_ES', 'Español', $english);

        $this->actingAs($admin)
            ->post(route('ronove.admin.languages.update'), [
                'locales' => ['es_ES'],
            ])
            ->assertRedirect(route('ronove.admin.languages.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($english->fresh()->is_enabled);
        $this->assertTrue($spanish->fresh()->is_enabled);
        $this->assertNull($spanish->fresh()->fallback_locale_id);
    }

    public function test_an_administrator_can_customize_a_language_flag(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $spanish = $this->locale('es_ES', 'Español');

        $this->actingAs($admin)
            ->post(route('ronove.admin.languages.update'), [
                'locales' => ['es_ES'],
                'flags' => ['es_ES' => 'mx'],
            ])
            ->assertRedirect(route('ronove.admin.languages.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('MX', $spanish->fresh()->flag_code);

        $this->actingAs($admin)
            ->get(route('ronove.admin.languages.index'))
            ->assertOk()
            ->assertSee('value="MX"', false)
            ->assertSee('🇲🇽');
    }

    public function test_global_locale_is_source_only_in_language_and_translation_admin(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $english = $this->locale('en', 'English');
        $spanish = $this->locale('es_ES', 'Español');

        $this->actingAs($admin)
            ->get(route('ronove.admin.languages.index'))
            ->assertOk()
            ->assertSee('Original content language')
            ->assertDontSee('value="en"', false)
            ->assertSee('value="es_ES"', false);

        $this->actingAs($admin)
            ->post(route('ronove.admin.languages.update'), ['locales' => ['en']])
            ->assertSessionHasErrors('locales.0');

        $this->actingAs($admin)
            ->post(route('ronove.admin.languages.update'), [])
            ->assertRedirect(route('ronove.admin.languages.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($english->fresh()->is_enabled);
        $this->assertFalse($spanish->fresh()->is_enabled);
    }

    private function locale(string $code, string $name, ?Locale $fallback = null): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => $name,
            'native_name' => $name,
            'is_enabled' => true,
            'position' => Locale::query()->count(),
            'fallback_locale_id' => $fallback?->id,
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

    /**
     * @param  array<string, string>  $values
     */
    private function publish(Post $post, Locale $locale, array $values): Translation
    {
        $resource = Resource::query()->firstOrCreate([
            'resource_type' => 'core.post',
            'resource_key' => (string) $post->id,
        ]);

        return Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $locale->id,
            'status' => Translation::PUBLISHED,
            'values' => $values,
        ]);
    }
}
