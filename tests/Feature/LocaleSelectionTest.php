<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\User;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Events\LocaleChanged;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\UserPreference;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
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

        $this->assertCount(1, $options);
        $this->assertContainsOnlyInstancesOf(LocaleOption::class, $options);
        $this->assertSame('es_ES', $current?->code);
        $this->assertTrue($current?->isCurrent);
        $this->assertSame([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_current' => true,
        ], $current?->jsonSerialize());
        $this->assertSame(route('ronove.locale.update'), app('ronove')->languageUpdateUrl());
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

    public function test_azuriom_global_locale_cannot_be_selected_as_a_ronove_language(): void
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
            ->assertSessionHasErrors('locale');

        $this->assertSame([], app('ronove')->languageOptions()->all());
    }
}
