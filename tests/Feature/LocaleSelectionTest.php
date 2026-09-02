<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Setting;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Events\LocaleChanged;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\UserPreference;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\PublicLanguagePage;
use Azuriom\Plugin\Ronove\Support\LocaleOption;
use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class LocaleSelectionTest extends TestCase
{
    public function test_a_guest_can_select_an_enabled_locale(): void
    {
        Event::fake([LocaleChanged::class]);

        Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_enabled' => true,
            'position' => 0,
        ]);
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Español',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);

        $response = $this->from('/ronove')->post('/ronove/locale', [
            'locale' => 'es_ES',
        ]);

        $response->assertRedirect('/ronove');
        $response->assertSessionHas(LocaleManager::SESSION_KEY, 'es_ES');
        $response->assertCookie(LocaleManager::COOKIE_NAME, 'es_ES');
        Event::assertDispatched(LocaleChanged::class, fn (LocaleChanged $event) => $event->locale === 'es_ES' && $event->userId === null);
    }

    public function test_a_disabled_locale_cannot_be_selected(): void
    {
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Español',
            'native_name' => 'Español',
            'is_enabled' => false,
            'position' => 0,
        ]);

        $this->from('/ronove')->post('/ronove/locale', ['locale' => 'es_ES'])
            ->assertRedirect('/ronove')
            ->assertSessionHasErrors('locale');
    }

    public function test_an_authenticated_users_selection_is_persisted(): void
    {
        Setting::updateSettings('locale', 'en');
        $locale = Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 0,
        ]);
        $user = User::query()->create([
            'name' => 'Ronove User',
            'email' => 'ronove@example.com',
            'password' => 'password',
            'role_id' => 1,
        ]);

        $this->actingAs($user)
            ->from('/ronove')
            ->post('/ronove/locale', ['locale' => 'es_ES'])
            ->assertRedirect('/ronove');

        $this->assertDatabaseHas('ronove_user_preferences', [
            'user_id' => $user->id,
            'locale_id' => $locale->id,
        ]);
        $this->assertSame(1, UserPreference::query()->count());
    }

    public function test_browser_language_is_used_when_no_session_or_cookie_exists(): void
    {
        Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_enabled' => true,
            'position' => 0,
        ]);
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Español',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);

        $this->get('/ronove', ['Accept-Language' => 'es-ES,es;q=0.9'])
            ->assertOk();

        $this->assertSame('es_ES', app()->getLocale());
    }

    public function test_the_public_page_uses_the_shared_language_buttons(): void
    {
        Setting::updateSettings('locale', 'en');
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 0,
        ]);

        $this->get('/ronove')
            ->assertOk()
            ->assertSee('Español')
            ->assertSee('name="locale"', false)
            ->assertSee('action="'.route('ronove.locale.update').'"', false);
    }

    public function test_the_public_page_can_be_disabled_without_disabling_language_selection(): void
    {
        Setting::updateSettings('locale', 'en');
        Setting::updateSettings(PublicLanguagePage::SETTING_KEY, '0');
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 0,
        ]);

        $this->assertFalse(app(PublicLanguagePage::class)->enabled());
        $this->assertFalse(app('ronove')->publicLanguagePageEnabled());
        $this->get('/ronove')->assertNotFound();

        $this->from('/')->post('/ronove/locale', ['locale' => 'es_ES'])
            ->assertRedirect('/')
            ->assertSessionHas(LocaleManager::SESSION_KEY, 'es_ES')
            ->assertSessionHasNoErrors();
    }

    public function test_the_theme_api_returns_presentation_safe_language_options(): void
    {
        Setting::updateSettings('locale', 'en');
        Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_enabled' => true,
            'position' => 0,
        ]);
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);
        app()->setLocale('es_ES');

        $options = app('ronove')->languageOptions();
        $current = app('ronove')->currentLanguage();

        $this->assertCount(2, $options);
        $this->assertContainsOnlyInstancesOf(LocaleOption::class, $options);
        $this->assertSame('es_ES', $current?->code);
        $this->assertTrue($current?->isCurrent);
        $this->assertSame([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_current' => true,
            'flag_code' => null,
        ], $current?->jsonSerialize());
        $this->assertSame(route('ronove.locale.update'), app('ronove')->languageUpdateUrl());
    }

    public function test_language_flags_are_exposed_to_the_theme_selector(): void
    {
        Setting::updateSettings('locale', 'en');
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'flag_code' => 'MX',
            'is_enabled' => true,
            'position' => 0,
        ]);

        $option = app('ronove')->languageOptions('es_ES')->firstWhere('code', 'es_ES');
        $html = view('ronove::language-selector')->render();

        $this->assertSame('MX', $option?->flagCode);
        $this->assertStringContainsString('🇲🇽', $html);
        $this->assertStringContainsString('Español', $html);
    }

    public function test_the_dropdown_can_render_as_an_icon_only_selector(): void
    {
        Setting::updateSettings('locale', 'en');
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 0,
        ]);

        $html = view('ronove::language-selector', ['showCurrentName' => false])->render();

        $this->assertStringContainsString('bi-translate', $html);
        $this->assertStringContainsString('visually-hidden', $html);
        $this->assertStringNotContainsString('<span class="ms-1">Español</span>', $html);
    }

    public function test_azuriom_global_locale_is_available_as_the_original_language(): void
    {
        Setting::updateSettings('locale', 'en');
        Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_enabled' => true,
            'position' => 0,
        ]);

        $this->from('/ronove')->post('/ronove/locale', ['locale' => 'en'])
            ->assertRedirect('/ronove')
            ->assertSessionHas(LocaleManager::SESSION_KEY, 'en')
            ->assertSessionHasNoErrors();

        $options = app('ronove')->languageOptions('en');
        $this->assertCount(1, $options);
        $this->assertSame('en', $options->first()->code);
        $this->assertSame('Original (English)', $options->first()->nativeName);
        $this->assertTrue($options->first()->isCurrent);
    }

    public function test_selecting_a_ronove_language_never_changes_azuriom_global_locale(): void
    {
        Event::fake([LocaleChanged::class]);
        Setting::updateSettings('locale', 'en');
        $spanish = Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);
        $user = User::query()->create([
            'name' => 'Language User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => 1,
        ]);

        $this->actingAs($user)
            ->from('/ronove')
            ->post('/ronove/locale', ['locale' => 'es_ES'])
            ->assertSessionHas(LocaleManager::SESSION_KEY, 'es_ES')
            ->assertSessionHasNoErrors();

        $this->assertSame('en', setting('locale'));
        $this->assertDatabaseHas('ronove_user_preferences', [
            'user_id' => $user->id,
            'locale_id' => $spanish->id,
        ]);

        $this->actingAs($user)
            ->from('/ronove')
            ->post('/ronove/locale', ['locale' => 'en'])
            ->assertSessionHas(LocaleManager::SESSION_KEY, 'en')
            ->assertSessionHasNoErrors();

        $this->assertSame('en', setting('locale'));
        $this->assertDatabaseMissing('ronove_user_preferences', ['user_id' => $user->id]);
        Event::assertDispatched(LocaleChanged::class, fn (LocaleChanged $event) => $event->locale === 'es_ES');
        Event::assertDispatched(LocaleChanged::class, fn (LocaleChanged $event) => $event->locale === 'en');
    }
}
