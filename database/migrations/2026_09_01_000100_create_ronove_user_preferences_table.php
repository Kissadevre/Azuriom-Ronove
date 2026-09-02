<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ronove_user_preferences');
    }
};
