<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\UserPreference;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Tests\TestCase;

class LocaleSelectionTest extends TestCase
{
    public function test_a_guest_can_select_an_enabled_locale(): void
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

        $response = $this->from('/ronove')->post('/ronove/locale', [
            'locale' => 'es_ES',
        ]);

        $response->assertRedirect('/ronove');
        $response->assertSessionHas(LocaleManager::SESSION_KEY, 'es_ES');
        $response->assertCookie(LocaleManager::COOKIE_NAME, 'es_ES');
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
        $locale = Locale::query()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
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
            ->post('/ronove/locale', ['locale' => 'en'])
            ->assertRedirect('/ronove');

        $this->assertDatabaseHas('ronove_user_preferences', [
            'user_id' => $user->id,
            'locale_id' => $locale->id,
        ]);
        $this->assertSame(1, UserPreference::query()->count());
    }
}
