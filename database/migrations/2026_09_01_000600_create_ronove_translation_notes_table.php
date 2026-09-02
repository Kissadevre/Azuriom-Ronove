<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ronove_translation_notes');
    }
};
