<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Role;
use Azuriom\Models\Setting;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Models\GlossaryTerm;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Tests\TestCase;

class AdministrativePermissionTest extends TestCase
{
    public function test_general_settings_and_language_management_have_separate_permissions(): void
    {
        $settingsManager = $this->userWithPermissions('Settings manager', [
            'admin.access', 'ronove.settings',
        ]);
        $languageManager = $this->userWithPermissions('Language manager', [
            'admin.access', 'ronove.languages',
        ]);

        $this->actingAs($settingsManager)
            ->get(route('ronove.admin.settings.index'))
            ->assertOk();
        $this->actingAs($settingsManager)
            ->get(route('ronove.admin.languages.index'))
            ->assertForbidden();

        $this->flushSession();
        $this->actingAs($languageManager)
            ->get(route('ronove.admin.languages.index'))
            ->assertOk();
        $this->actingAs($languageManager)
            ->get(route('ronove.admin.settings.index'))
            ->assertForbidden();
    }

    public function test_glossary_permission_controls_term_management(): void
    {
        Setting::updateSettings('locale', 'en');
        $spanish = Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);
        $translator = $this->userWithPermissions('Translator', [
            'admin.access', 'ronove.translations',
        ]);
        $glossaryManager = $this->userWithPermissions('Glossary manager', [
            'admin.access', 'ronove.glossary',
        ]);

        $this->actingAs($translator)
            ->get(route('ronove.admin.glossary.index'))
            ->assertForbidden();
        $this->actingAs($translator)
            ->post(route('ronove.admin.glossary.store'), [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
                'source_text' => 'Server',
                'translated_text' => 'Servidor',
            ])
            ->assertForbidden();

        $this->flushSession();
        $this->actingAs($glossaryManager)
            ->get(route('ronove.admin.glossary.index'))
            ->assertOk();
        $this->actingAs($glossaryManager)
            ->post(route('ronove.admin.glossary.store'), [
                'scope' => GlossaryTerm::GLOBAL_SCOPE,
                'locale' => $spanish->code,
                'source_text' => 'Server',
                'translated_text' => 'Servidor',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $term = GlossaryTerm::query()->firstOrFail();

        $this->actingAs($glossaryManager)
            ->delete(route('ronove.admin.glossary.destroy', $term))
            ->assertRedirect();

        $this->assertDatabaseCount('ronove_glossary_terms', 0);
    }

    public function test_translators_can_read_suggestions_without_the_glossary_management_link(): void
    {
        Setting::updateSettings('locale', 'en');
        $translator = $this->userWithPermissions('Suggestion reader', [
            'admin.access', 'ronove.translations',
        ]);
        Locale::query()->create([
            'code' => 'es_ES',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_enabled' => true,
            'position' => 1,
        ]);
        $post = $translator->posts()->create([
            'title' => 'Server status',
            'description' => 'Status description',
            'slug' => 'server-status',
            'content' => '<p>Server status</p>',
            'is_pinned' => false,
            'published_at' => now()->subMinute(),
        ]);

        $this->actingAs($translator)
            ->get(route('ronove.admin.translations.edit', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => 'es_ES',
            ]))
            ->assertOk()
            ->assertSee('Glossary suggestions')
            ->assertDontSee('Manage glossary');
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(string $name, array $permissions): User
    {
        $role = Role::query()->create([
            'name' => $name,
            'color' => '6c757d',
            'power' => Role::query()->max('power') + 1,
            'is_admin' => false,
        ]);

        foreach ($permissions as $permission) {
            $role->permissions()->create(['permission' => $permission]);
        }

        return User::query()->forceCreate([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
        ]);
    }
}
