<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\User;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Providers\Resources\PostResourceProvider;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Tests\TestCase;

class PostTranslationTest extends TestCase
{
    public function test_the_core_post_provider_exposes_only_visible_translatable_fields(): void
    {
        $fields = app(ResourceRegistry::class)->get('core.post')->fields();

        $this->assertSame(['title', 'content'], array_keys($fields));
        $this->assertArrayNotHasKey('description', $fields);
        $this->assertArrayNotHasKey('slug', $fields);
        $this->assertSame('core', app(ResourceRegistry::class)->integrationFor('core.post')->id);
    }

    public function test_the_translation_center_groups_core_resources_under_azuriom(): void
    {
        app(ResourceRegistry::class)->register(new class extends PostResourceProvider
        {
            public function type(): string
            {
                return 'core.featured-post';
            }

            public function label(): string
            {
                return 'Featured posts';
            }
        }, 'core');

        $admin = User::query()->create([
            'name' => 'Ronove Admin',
            'email' => 'ronove-admin@example.com',
            'password' => 'password',
            'role_id' => 1,
        ]);
        $admin->role->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get('/admin/ronove/translations')
            ->assertOk()
            ->assertSee('Azuriom')
            ->assertSee('News posts')
            ->assertSee('Pages')
            ->assertSee('General messages')
            ->assertSee('Featured posts')
            ->assertSee(route('ronove.admin.translations.integration', 'core'));

        $this->actingAs($admin)
            ->get('/admin/ronove/translations/integration/core')
            ->assertOk()
            ->assertSee('Azuriom')
            ->assertSee('News posts')
            ->assertSee('Featured posts')
            ->assertSee(route('ronove.admin.translations.integration', [
                'integration' => 'core',
                'type' => 'core.featured-post',
            ]));
    }

    public function test_published_post_fields_use_selected_and_original_fallbacks(): void
    {
        [$post, $english, $spanish, $resource] = $this->fixtures();

        Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $english->id,
            'status' => Translation::PUBLISHED,
            'values' => ['content' => '<p>Global content</p>'],
        ]);
        Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $spanish->id,
            'status' => Translation::PUBLISHED,
            'values' => ['title' => 'Título traducido'],
        ]);

        $values = app(TranslationResolver::class)->values('core.post', $post, 'es_ES');

        $this->assertSame('Título traducido', $values['title']);
        $this->assertSame('<p>Original content</p>', $values['content']);
        $this->assertSame('Original SEO description', $post->description);
        $this->assertSame('original-slug', $post->slug);
    }

    public function test_drafts_are_not_rendered_publicly(): void
    {
        [$post, , $spanish, $resource] = $this->fixtures();

        Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $spanish->id,
            'status' => Translation::DRAFT,
            'values' => ['title' => 'Borrador privado'],
        ]);

        $values = app(TranslationResolver::class)->values('core.post', $post, 'es_ES');

        $this->assertSame('Original title', $values['title']);
    }

    public function test_the_post_view_receives_the_selected_translation_without_changing_the_slug(): void
    {
        [$post, , $spanish, $resource] = $this->fixtures();

        Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $spanish->id,
            'status' => Translation::PUBLISHED,
            'values' => [
                'title' => 'Noticia traducida',
                'content' => '<p>Contenido traducido</p>',
            ],
        ]);

        $this->withSession([LocaleManager::SESSION_KEY => 'es_ES'])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertSee('Noticia traducida')
            ->assertSee('Contenido traducido', false);

        $this->assertSame('original-slug', $post->fresh()->slug);
        $this->assertSame('Original SEO description', $post->fresh()->description);
    }

    public function test_the_global_locale_cannot_be_opened_or_saved_as_a_translation_target(): void
    {
        [$post, $english, $spanish] = $this->fixtures();
        $admin = User::query()->create([
            'name' => 'Translation Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => 1,
        ]);
        $admin->role->forceFill(['is_admin' => true])->save();
        $route = [
            'type' => 'core.post',
            'key' => $post->id,
        ];

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.edit', $route + ['locale' => $english->code]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.edit', $route + ['locale' => $spanish->code]))
            ->assertOk();

        $this->actingAs($admin)
            ->from(route('ronove.admin.translations.edit', $route + ['locale' => $spanish->code]))
            ->put(route('ronove.admin.translations.update', $route), [
                'locale' => $english->code,
                'status' => Translation::DRAFT,
                'values' => ['title' => 'Must not be saved'],
            ])
            ->assertSessionHasErrors('locale');

        $this->assertDatabaseMissing('ronove_translations', ['locale_id' => $english->id]);
    }

    public function test_deleting_a_post_removes_its_ronove_translations(): void
    {
        [$post, , $spanish, $resource] = $this->fixtures();
        Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $spanish->id,
            'status' => Translation::PUBLISHED,
            'values' => ['title' => 'Traducción'],
        ]);

        $post->delete();

        $this->assertDatabaseMissing('ronove_resources', ['id' => $resource->id]);
        $this->assertDatabaseCount('ronove_translations', 0);
    }

    public function test_source_hash_changes_when_the_original_visible_text_changes(): void
    {
        [$post] = $this->fixtures();
        $provider = app(ResourceRegistry::class)->get('core.post');
        $resolver = app(TranslationResolver::class);
        $before = $resolver->sourceHash($provider, $post);

        $post->update(['title' => 'Updated original title']);

        $this->assertNotSame($before, $resolver->sourceHash($provider, $post->fresh()));
    }

    private function fixtures(): array
    {
        Setting::updateSettings('locale', 'en');

        $user = User::query()->create([
            'name' => 'Author',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => 1,
        ]);
        $post = $user->posts()->create([
            'title' => 'Original title',
            'description' => 'Original SEO description',
            'slug' => 'original-slug',
            'content' => '<p>Original content</p>',
            'is_pinned' => false,
            'published_at' => now()->subMinute(),
        ]);
        $english = Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_enabled' => true,
            'position' => 0,
        ]);
        $spanish = Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Español',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);
        $resource = Resource::query()->create([
            'resource_type' => 'core.post',
            'resource_key' => (string) $post->id,
        ]);

        return [$post, $english, $spanish, $resource];
    }
}
