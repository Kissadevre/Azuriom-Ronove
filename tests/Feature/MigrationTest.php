<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class MigrationTest extends TestCase
{
    public function test_plugin_tables_are_created_by_separate_migrations(): void
    {
        $this->assertTrue(Schema::hasColumns('ronove_locales', [
            'code', 'name', 'native_name', 'is_enabled', 'position', 'fallback_locale_id',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_user_preferences', [
            'user_id', 'locale_id',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_resources', [
            'resource_type', 'resource_key',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_translations', [
            'resource_id', 'locale_id', 'status', 'values', 'source_hash',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_glossary_terms', [
            'scope', 'locale_id', 'source_text', 'source_key', 'translated_text', 'context',
        ]));
        $this->assertTrue(Schema::hasColumns('ronove_translation_notes', [
            'resource_id', 'locale_id', 'note',
        ]));
    }
}
