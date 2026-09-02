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
            $table->string('review_status', 30)->default('draft')->after('status');
            $table->unsignedInteger('reviewed_by')->nullable()->after('source_hash');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_feedback')->nullable()->after('reviewed_at');

            $table->index(
                ['locale_id', 'review_status'],
                'ronove_translations_locale_review_index'
            );
            $table->foreign('reviewed_by', 'ronove_translations_reviewer_foreign')
                ->references('id')->on('users')->nullOnDelete();
        });

        DB::table('ronove_translations')
            ->where('status', 'published')
            ->update(['review_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('ronove_translations', function (Blueprint $table) {
            $table->dropForeign('ronove_translations_reviewer_foreign');
            $table->dropIndex('ronove_translations_locale_review_index');
            $table->dropColumn([
                'review_status', 'reviewed_by', 'reviewed_at', 'review_feedback',
            ]);
        });
    }
};
