<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Ronove 1.0 public schema baseline.
 * Every schema change after 1.0 must be implemented in a new dated migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ronove_locales', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 20)->unique('ronove_locales_code_unique');
            $table->string('name', 100);
            $table->string('native_name', 100);
            $table->string('flag_code', 2)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('fallback_locale_id')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'position'], 'ronove_locales_enabled_position_index');
            $table->foreign('fallback_locale_id', 'ronove_locales_fallback_foreign')
                ->references('id')->on('ronove_locales')->nullOnDelete();
        });

        Schema::create('ronove_user_preferences', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique('ronove_user_preferences_user_unique');
            $table->unsignedInteger('locale_id');
            $table->timestamps();

            $table->foreign('user_id', 'ronove_user_preferences_user_foreign')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('locale_id', 'ronove_user_preferences_locale_foreign')
                ->references('id')->on('ronove_locales')->cascadeOnDelete();
        });

        Schema::create('ronove_resources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('resource_type', 80);
            $table->string('resource_key', 100);
            $table->timestamps();

            $table->unique(
                ['resource_type', 'resource_key'],
                'ronove_resources_type_key_unique'
            );
        });

        Schema::create('ronove_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedInteger('locale_id');
            $table->string('status', 20)->default('draft');
            $table->string('review_status', 30)->default('draft');
            $table->longText('values');
            $table->longText('published_values')->nullable();
            $table->char('published_source_hash', 64)->nullable();
            $table->char('source_hash', 64)->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_feedback')->nullable();
            $table->timestamps();

            $table->unique(
                ['resource_id', 'locale_id'],
                'ronove_translations_resource_locale_unique'
            );
            $table->index(
                ['locale_id', 'status'],
                'ronove_translations_locale_status_index'
            );
            $table->index(
                ['locale_id', 'review_status'],
                'ronove_translations_locale_review_index'
            );
            $table->foreign('resource_id', 'ronove_translations_resource_foreign')
                ->references('id')->on('ronove_resources')->cascadeOnDelete();
            $table->foreign('locale_id', 'ronove_translations_locale_foreign')
                ->references('id')->on('ronove_locales')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'ronove_translations_reviewer_foreign')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ronove_glossary_terms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('scope', 80);
            $table->unsignedInteger('locale_id');
            $table->string('source_text', 100);
            $table->char('source_key', 64);
            $table->string('translated_text', 191);
            $table->text('context')->nullable();
            $table->timestamps();

            $table->unique(
                ['scope', 'locale_id', 'source_key'],
                'ronove_glossary_scope_locale_source_unique'
            );
            $table->index(
                ['locale_id', 'scope'],
                'ronove_glossary_locale_scope_index'
            );
            $table->foreign('locale_id', 'ronove_glossary_locale_foreign')
                ->references('id')->on('ronove_locales')->cascadeOnDelete();
        });

        Schema::create('ronove_translation_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedInteger('locale_id');
            $table->text('note');
            $table->timestamps();

            $table->unique(
                ['resource_id', 'locale_id'],
                'ronove_notes_resource_locale_unique'
            );
            $table->foreign('resource_id', 'ronove_notes_resource_foreign')
                ->references('id')->on('ronove_resources')->cascadeOnDelete();
            $table->foreign('locale_id', 'ronove_notes_locale_foreign')
                ->references('id')->on('ronove_locales')->cascadeOnDelete();
        });

        Schema::create('ronove_translation_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('translation_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 30);
            $table->string('status', 20);
            $table->string('review_status', 30);
            $table->longText('values');
            $table->char('source_hash', 64)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(
                ['translation_id', 'created_at'],
                'ronove_revisions_translation_created_index'
            );
            $table->foreign('translation_id', 'ronove_revisions_translation_foreign')
                ->references('id')->on('ronove_translations')->cascadeOnDelete();
            $table->foreign('user_id', 'ronove_revisions_user_foreign')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ronove_translation_revisions');
        Schema::dropIfExists('ronove_translation_notes');
        Schema::dropIfExists('ronove_glossary_terms');
        Schema::dropIfExists('ronove_translations');
        Schema::dropIfExists('ronove_resources');
        Schema::dropIfExists('ronove_user_preferences');
        Schema::dropIfExists('ronove_locales');
    }
};
