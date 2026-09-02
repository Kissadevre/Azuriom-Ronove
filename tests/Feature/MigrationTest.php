<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Role;
use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationTest extends TestCase
{
    public function test_public_release_schema_and_follow_up_migrations_are_available(): void
    {
        $this->assertCount(2, glob($this->migrationDirectory().'/*.php') ?: []);

        $this->assertTrue(Schema::hasColumns('ronove_locales', [
            'code', 'name', 'native_name', 'flag_code', 'is_enabled', 'position', 'fallback_locale_id',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_user_preferences', [
            'user_id', 'locale_id',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_resources', [
            'resource_type', 'resource_key',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_translations', [
            'resource_id', 'locale_id', 'status', 'review_status', 'values', 'source_hash',
            'published_values', 'published_source_hash', 'reviewed_by', 'reviewed_at',
            'review_feedback',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_glossary_terms', [
            'scope', 'locale_id', 'source_text', 'source_key', 'translated_text', 'context',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_translation_notes', [
            'resource_id', 'locale_id', 'note',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_translation_revisions', [
            'translation_id', 'user_id', 'action', 'status', 'review_status',
            'values', 'source_hash', 'feedback',
        ]));
    }

    public function test_split_permissions_are_copied_for_existing_roles_and_are_reversible(): void
    {
        $role = Role::query()->create([
            'name' => 'Legacy Ronove manager',
            'color' => '6c757d',
            'power' => 1,
            'is_admin' => false,
        ]);
        $role->permissions()->createMany([
            ['permission' => 'ronove.settings'],
            ['permission' => 'ronove.translations'],
        ]);
        $migration = require $this->migrationDirectory().'/2026_09_02_000000_copy_split_admin_permissions.php';

        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('permissions')->where([
            'role_id' => $role->id,
            'permission' => 'ronove.languages',
        ])->count());
        $this->assertSame(1, DB::table('permissions')->where([
            'role_id' => $role->id,
            'permission' => 'ronove.glossary',
        ])->count());

        $migration->down();

        $this->assertDatabaseHas('permissions', [
            'role_id' => $role->id,
            'permission' => 'ronove.settings',
        ]);
        $this->assertDatabaseHas('permissions', [
            'role_id' => $role->id,
            'permission' => 'ronove.translations',
        ]);
        $this->assertDatabaseMissing('permissions', [
            'role_id' => $role->id,
            'permission' => 'ronove.languages',
        ]);
        $this->assertDatabaseMissing('permissions', [
            'role_id' => $role->id,
            'permission' => 'ronove.glossary',
        ]);
    }

    public function test_the_consolidated_migration_is_reversible(): void
    {
        $migration = require $this->migrationDirectory().'/2026_09_01_000000_create_ronove_locales_table.php';
        $tables = [
            'ronove_translation_revisions',
            'ronove_translation_notes',
            'ronove_glossary_terms',
            'ronove_translations',
            'ronove_resources',
            'ronove_user_preferences',
            'ronove_locales',
        ];

        $migration->down();

        foreach ($tables as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }

        $migration->up();

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    private function migrationDirectory(): string
    {
        return dirname(__DIR__, 2).'/database/migrations';
    }
}
