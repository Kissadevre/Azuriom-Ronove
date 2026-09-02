<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ronove_locales', function (Blueprint $table) {
            $table->unsignedInteger('fallback_locale_id')->nullable()->after('position');
            $table->foreign('fallback_locale_id', 'ronove_locales_fallback_foreign')
                ->references('id')->on('ronove_locales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ronove_locales', function (Blueprint $table) {
            $table->dropForeign('ronove_locales_fallback_foreign');
            $table->dropColumn('fallback_locale_id');
        });
    }
};
