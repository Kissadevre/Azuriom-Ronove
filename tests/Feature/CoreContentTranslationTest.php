<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Page;
use Azuriom\Models\Setting;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Azuriom\Plugin\Ronove\Tests\TestCase;

class CoreContentTranslationTest extends TestCase
{
    public function test_core_pages_expose_only_visible_non_seo_fields(): void
    {
        $registry = app(ResourceRegistry::class);
        $fields = $registry->get('core.page')->fields();

        $this->assertSame(['title', 'content'], array_keys($fields));
        $this->assertArrayNotHasKey('description', $fields);
        $this->assertArrayNotHasKey('slug', $fields);
        $this->assertSame('core', $registry->integrationFor('core.page')->id);
    }

    public function test_a_public_page_uses_translated_visible_content_without_changing_its_source(): void
    {
        $spanish = $this->spanishLocale();
        $page = Page::query()->create([
            'title' => 'Original page',
            'description' => 'Original page SEO description',
            'slug' => 'original-page',
            'content' => '<p>Original page content</p>',
            'is_enabled' => true,
        ]);
        $resource = $this->publish('core.page', (string) $page->id, $spanish, [
            'title' => 'Página traducida',
            'content' => '<p>Contenido traducido</p>',
        ]);

        $this->withSession([LocaleManager::SESSION_KEY => 'es_ES'])
            ->get('/original-page')
            ->assertOk()
            ->assertSee('Página traducida')
            ->assertSee('<p>Contenido traducido</p>', false)
            ->assertSee('Original page SEO description');

        $this->assertSame('original-page', $page->fresh()->slug);
        $this->assertSame('Original page', $page->fresh()->title);

        $page->delete();
        $this->assertDatabaseMissing('ronove_resources', ['id' => $resource->id]);
    }

    public function test_general_messages_are_overlaid_only_for_the_current_public_request(): void
    {
        $spanish = $this->spanishLocale();
        Setting::updateSettings([
            'home_message' => '<p>Original home message</p>',
            'welcome_alert' => '<p>Original welcome alert</p>',
            'maintenance.message' => '<p>Original maintenance message</p>',
            'conditions' => 'Original registration conditions',
            'copyright' => 'Original copyright {year}',
        ]);

        $this->publish('core.site-message', 'home_message', $spanish, [
            'content' => '<p>Mensaje de inicio traducido</p>',
        ]);
        $this->publish('core.site-message', 'welcome_alert', $spanish, [
            'content' => '<p>Aviso de bienvenida traducido</p>',
        ]);
        $this->publish('core.site-message', 'maintenance.message', $spanish, [
            'content' => '<p>Mensaje de mantenimiento traducido</p>',
        ]);
        $this->publish('core.registration-conditions', 'conditions', $spanish, [
            'content' => 'Acepto las **condiciones traducidas**',
        ]);
        $this->publish('core.footer', 'copyright', $spanish, [
            'content' => 'Derechos traducidos {year}',
        ]);

        $this->withSession([LocaleManager::SESSION_KEY => 'es_ES'])
            ->get('/')
            ->assertOk()
            ->assertSee('<p>Mensaje de inicio traducido</p>', false)
            ->assertSee('<p>Aviso de bienvenida traducido</p>', false)
            ->assertSee('Derechos traducidos '.date('Y'));

        $this->withSession([LocaleManager::SESSION_KEY => 'es_ES'])
            ->get('/user/register')
            ->assertOk()
            ->assertSee('condiciones traducidas');

        Setting::updateSettings('maintenance.enabled', true);

        $this->withSession([LocaleManager::SESSION_KEY => 'es_ES'])
            ->get('/maintenance-preview')
            ->assertStatus(503)
            ->assertSee('<p>Mensaje de mantenimiento traducido</p>', false);

        $admin = User::query()->create([
            'name' => 'Settings Admin',
            'email' => 'settings-admin@example.com',
            'password' => 'password',
            'role_id' => 1,
        ]);
        $admin->role->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get('/admin/settings/home')
            ->assertOk()
            ->assertSee('Original home message')
            ->assertDontSee('Mensaje de inicio traducido');
    }

    public function test_url_based_registration_conditions_are_never_translatable(): void
    {
        Setting::updateSettings('conditions', 'https://example.com/terms');
        $provider = app(ResourceRegistry::class)->get('core.registration-conditions');

        $this->assertNull($provider->find('conditions'));
        $this->assertSame(0, $provider->query()->count());
    }

    public function test_general_message_providers_use_the_expected_editors(): void
    {
        $registry = app(ResourceRegistry::class);

        $this->assertSame(
            TranslatableField::RICH_TEXT,
            $registry->get('core.site-message')->fields()['content']->type,
        );
        $this->assertSame(
            TranslatableField::MARKDOWN,
            $registry->get('core.registration-conditions')->fields()['content']->type,
        );
        $this->assertSame(
            TranslatableField::TEXT,
            $registry->get('core.footer')->fields()['content']->type,
        );
    }

    private function spanishLocale(): Locale
    {
        Setting::updateSettings('locale', 'en');

        Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_enabled' => true,
            'position' => 0,
        ]);

        return Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Español',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function publish(string $type, string $key, Locale $locale, array $values): Resource
    {
        $resource = Resource::query()->create([
            'resource_type' => $type,
            'resource_key' => $key,
        ]);
        Translation::query()->create([
            'resource_id' => $resource->id,
            'locale_id' => $locale->id,
            'status' => Translation::PUBLISHED,
            'values' => $values,
        ]);

        return $resource;
    }
}
