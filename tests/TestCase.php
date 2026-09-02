<?php

namespace Azuriom\Plugin\Ronove\Tests;

use Azuriom\Http\Controllers\InstallController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $this->setEnvironmentVariables([
            'APP_ENV' => 'testing',
            'APP_KEY' => InstallController::TEMP_KEY,
            'APP_CONFIG_CACHE' => __DIR__.'/cache/ronove-config.php',
            'APP_ROUTES_CACHE' => __DIR__.'/cache/ronove-routes.php',
            'CACHE_DRIVER' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_PATH' => ':memory:',
            'DB_URL' => '(null)',
            'LOG_CHANNEL' => 'null',
            'SESSION_DRIVER' => 'array',
        ]);

        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Ronove tests refuse to run outside SQLite memory.');
        }

        config([
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.previous_keys' => [],
        ]);
        DB::purge('sqlite');

        $plugin = $app['plugins']->findDescription('ronove');

        if ($plugin === null) {
            throw new RuntimeException('The Ronove plugin manifest could not be loaded.');
        }

        foreach ($plugin->providers as $providerClass) {
            $provider = new $providerClass($app);
            $provider->bindPlugin($plugin);
            $app->register($provider);
        }

        $app['router']->getRoutes()->refreshNameLookups();
        $app['router']->getRoutes()->refreshActionLookups();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        (require dirname(__DIR__, 3).'/database/migrations/2014_10_12_000000_create_users_table.php')->up();
        (require dirname(__DIR__, 3).'/database/migrations/2019_08_15_000000_create_roles_table.php')->up();
        (require dirname(__DIR__, 3).'/database/migrations/2019_10_06_000000_create_bans_table.php')->up();
        (require dirname(__DIR__, 3).'/database/migrations/2023_06_03_add_password_changed_at_to_users_table.php')->up();

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'User',
            'color' => '6c757d',
            'power' => 0,
            'is_admin' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pluginMigrations = glob(dirname(__DIR__).'/database/migrations/*.php') ?: [];
        sort($pluginMigrations);

        foreach ($pluginMigrations as $migration) {
            (require $migration)->up();
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function setEnvironmentVariables(array $variables): void
    {
        foreach ($variables as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
