<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ronove_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedInteger('locale_id');
            $table->string('status', 20)->default('draft');
            $table->longText('values');
            $table->char('source_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['resource_id', 'locale_id'],
                'ronove_translations_resource_locale_unique'
            );
            $table->index(
                ['locale_id', 'status'],
                'ronove_translations_locale_status_index'
            );
            $table->foreign('resource_id', 'ronove_translations_resource_foreign')
                ->references('id')->on('ronove_resources')->cascadeOnDelete();
            $table->foreign('locale_id', 'ronove_translations_locale_foreign')
                ->references('id')->on('ronove_locales')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ronove_translations');
    }
};
