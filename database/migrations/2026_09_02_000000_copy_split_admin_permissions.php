<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $this->copyPermission('ronove.settings', 'ronove.languages');
        $this->copyPermission('ronove.translations', 'ronove.glossary');
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->whereIn('permission', [
            'ronove.languages',
            'ronove.glossary',
        ])->delete();
    }

    private function copyPermission(string $source, string $target): void
    {
        DB::table('permissions')
            ->where('permission', $source)
            ->pluck('role_id')
            ->each(function (int $roleId) use ($target) {
                $permission = [
                    'permission' => $target,
                    'role_id' => $roleId,
                ];

                if (! DB::table('permissions')->where($permission)->exists()) {
                    DB::table('permissions')->insert($permission);
                }
            });
    }
};
