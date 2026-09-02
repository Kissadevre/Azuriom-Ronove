<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
