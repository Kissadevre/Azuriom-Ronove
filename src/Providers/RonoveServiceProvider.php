<?php

namespace Azuriom\Plugin\Ronove\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;
use Azuriom\Plugin\Ronove\RonoveManager;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;

class RonoveServiceProvider extends BasePluginServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(RonoveManager::class);
        $this->app->alias(RonoveManager::class, 'ronove');
    }

    public function boot(): void
    {
        $this->loadViews();
        $this->loadTranslations();
        $this->loadMigrations();
        $this->registerRouteDescriptions();
        $this->registerAdminNavigation();

        Permission::registerPermissions([
            'ronove.settings' => 'ronove::admin.permissions.settings',
            'ronove.translations' => 'ronove::admin.permissions.translations',
            'ronove.publish' => 'ronove::admin.permissions.publish',
        ]);
    }

    protected function routeDescriptions(): array
    {
        return [
            'ronove.index' => trans('ronove::messages.language'),
        ];
    }

    protected function adminNavigation(): array
    {
        return [
            'ronove' => [
                'name' => trans('ronove::admin.title'),
                'type' => 'dropdown',
                'icon' => 'bi bi-translate',
                'permission' => ['ronove.settings', 'ronove.translations'],
                'route' => 'ronove.admin.*',
                'items' => [
                    'ronove.admin.languages.index' => [
                        'name' => trans('ronove::admin.nav.languages'),
                        'permission' => 'ronove.settings',
                    ],
                    'ronove.admin.translations.index' => [
                        'name' => trans('ronove::admin.nav.translations'),
                        'permission' => 'ronove.translations',
                    ],
                ],
            ],
        ];
    }
}
