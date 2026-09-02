<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ronove_resources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('resource_type', 80);
            $table->string('resource_key', 100);
            $table->timestamps();

            $table->unique(
                ['resource_type', 'resource_key'],
                'ronove_resources_type_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ronove_resources');
    }
};
