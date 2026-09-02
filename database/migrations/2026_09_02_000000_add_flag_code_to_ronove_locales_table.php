<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ronove_locales', function (Blueprint $table) {
            $table->string('flag_code', 2)->nullable()->after('native_name');
        });

        $defaults = [
            'ca' => 'ES', 'cs' => 'CZ', 'de' => 'DE', 'en' => 'GB', 'es' => 'ES',
            'fi' => 'FI', 'fr' => 'FR', 'hu' => 'HU', 'id' => 'ID', 'ko' => 'KR',
            'lt' => 'LT', 'nl' => 'NL', 'pl' => 'PL', 'pt' => 'PT', 'ru' => 'RU',
            'sv' => 'SE', 'tr' => 'TR', 'uk' => 'UA', 'zh' => 'CN',
        ];

        DB::table('ronove_locales')->select(['id', 'code'])->orderBy('id')->each(function ($locale) use ($defaults) {
            $parts = preg_split('/[-_]/', (string) $locale->code, 2);
            $language = strtolower($parts[0] ?? '');
            $region = isset($parts[1]) && strlen($parts[1]) === 2
                ? strtoupper($parts[1])
                : ($defaults[$language] ?? null);

            if ($region !== null) {
                DB::table('ronove_locales')->where('id', $locale->id)->update(['flag_code' => $region]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ronove_locales', function (Blueprint $table) {
            $table->dropColumn('flag_code');
        });
    }
};
