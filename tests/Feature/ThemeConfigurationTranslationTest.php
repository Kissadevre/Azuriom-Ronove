<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Extensions\Theme\ThemeManager;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Resource;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\LocalizedThemeConfiguration;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\ThemeTranslationManifestLoader;
use Azuriom\Plugin\Ronove\Services\ThemeTranslationRegistrar;
use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ThemeConfigurationTranslationTest extends TestCase
{
    public function test_published_theme_content_is_overlaid_only_for_the_public_request(): void
    {
        Setting::updateSettings('locale', 'en');
        Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_enabled' => true,
            'position' => 0,
        ]);
        $spanish = Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);
        $source = [
            'home' => [
                'hero' => [
                    'title' => 'Original hero',
                    'description' => 'Original description',
                ],
                'services' => ['items' => [['title' => 'Original service']]],
            ],
        ];
        Setting::updateSettings('themes.config.fixture', $source);
        config()->set('theme', $source);

        $themes = $this->createMock(ThemeManager::class);
        $themes->method('currentTheme')->willReturn('fixture');
        $themes->method('path')->with('ronove.php', 'fixture')
            ->willReturn(dirname(__DIR__).'/fixtures/theme/ronove.php');
        app()->instance(ThemeTranslationManifestLoader::class, new ThemeTranslationManifestLoader($themes));
        app()->forgetInstance(ThemeTranslationRegistrar::class);
        app()->forgetInstance(LocalizedThemeConfiguration::class);

        $manifest = app(ThemeTranslationRegistrar::class)->registerActive();

        $this->assertNotNull($manifest);
        $this->assertSame('theme.fixture', $manifest->integrationId);
        $this->assertTrue(app(ResourceRegistry::class)->has('theme.fixture.hero'));

        $hero = Resource::query()->create([
            'resource_type' => 'theme.fixture.hero',
            'resource_key' => 'themes.config.fixture',
        ]);
        Translation::query()->create([
            'resource_id' => $hero->id,
            'locale_id' => $spanish->id,
            'status' => Translation::PUBLISHED,
            'values' => [
                'title' => 'Hero traducido',
                'description' => 'Descripción traducida',
            ],
        ]);
        $services = Resource::query()->create([
            'resource_type' => 'theme.fixture.services',
            'resource_key' => 'themes.config.fixture',
        ]);
        Translation::query()->create([
            'resource_id' => $services->id,
            'locale_id' => $spanish->id,
            'status' => Translation::PUBLISHED,
            'values' => ['service_alpha_title' => 'Servicio traducido'],
        ]);

        Route::middleware('web')->get('/ronove-theme-fixture', fn () => response()->json(config('theme')));
        Route::middleware('web')->name('admin.ronove-theme-fixture')->get(
            '/ronove-theme-fixture-admin',
            fn () => response()->json(config('theme')),
        );

        $this->withSession([LocaleManager::SESSION_KEY => 'es_ES'])
            ->get('/ronove-theme-fixture')
            ->assertOk()
            ->assertJsonPath('home.hero.title', 'Hero traducido')
            ->assertJsonPath('home.hero.description', 'Descripción traducida')
            ->assertJsonPath('home.services.items.0.title', 'Servicio traducido');

        $this->withSession([LocaleManager::SESSION_KEY => 'es_ES'])
            ->get('/ronove-theme-fixture-admin')
            ->assertOk()
            ->assertJsonPath('home.hero.title', 'Original hero')
            ->assertJsonPath('home.services.items.0.title', 'Original service');

        $this->assertSame('Original hero', Setting::query()
            ->where('name', 'themes.config.fixture')
            ->firstOrFail()
            ->value['home']['hero']['title']);
    }
}
