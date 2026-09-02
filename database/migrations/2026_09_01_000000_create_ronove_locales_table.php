<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ronove_locales', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 20)->unique('ronove_locales_code_unique');
            $table->string('name', 100);
            $table->string('native_name', 100);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'position'], 'ronove_locales_enabled_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ronove_locales');
    }
};
