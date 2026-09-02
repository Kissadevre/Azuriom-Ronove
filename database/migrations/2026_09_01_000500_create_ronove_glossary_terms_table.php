<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ronove_glossary_terms');
    }
};
