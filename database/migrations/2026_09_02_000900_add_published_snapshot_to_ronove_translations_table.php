<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ronove_translations', function (Blueprint $table) {
            $table->longText('published_values')->nullable()->after('values');
            $table->char('published_source_hash', 64)->nullable()->after('published_values');
        });

        DB::table('ronove_translations')
            ->where('status', 'published')
            ->select(['id', 'values', 'source_hash'])
            ->orderBy('id')
            ->chunkById(100, function ($translations) {
                foreach ($translations as $translation) {
                    DB::table('ronove_translations')
                        ->where('id', $translation->id)
                        ->update([
                            'published_values' => $translation->values,
                            'published_source_hash' => $translation->source_hash,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ronove_translations', function (Blueprint $table) {
            $table->dropColumn(['published_values', 'published_source_hash']);
        });
    }
};
