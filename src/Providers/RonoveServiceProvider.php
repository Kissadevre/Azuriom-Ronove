<?php

namespace Azuriom\Plugin\Ronove\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Http\Kernel;
use Azuriom\Models\ActionLog;
use Azuriom\Models\Permission;
use Azuriom\Models\Post;
use Azuriom\Plugin\Ronove\Middleware\SetLocale;
use Azuriom\Plugin\Ronove\Providers\Resources\PostResourceProvider;
use Azuriom\Plugin\Ronove\RonoveManager;
use Azuriom\Plugin\Ronove\Services\LanguageSwitcher;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\View\Composers\PostTranslationComposer;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\View;

class RonoveServiceProvider extends BasePluginServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(LocaleManager::class);
        $this->app->singleton(LanguageSwitcher::class);
        $this->app->singleton(TranslationResolver::class);
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
        $this->registerLocaleMiddleware();
        $this->registerResources();
        View::composer(['home', 'posts.index', 'posts.show'], PostTranslationComposer::class);

        Permission::registerPermissions([
            'ronove.settings' => 'ronove::admin.permissions.settings',
            'ronove.translations' => 'ronove::admin.permissions.translations',
            'ronove.publish' => 'ronove::admin.permissions.publish',
        ]);

        ActionLog::registerLogs('ronove.settings.updated', [
            'icon' => 'translate',
            'color' => 'warning',
            'message' => 'ronove::admin.logs.settings_updated',
        ]);
        ActionLog::registerLogs([
            'ronove.translations.saved' => [
                'icon' => 'translate',
                'color' => 'success',
                'message' => 'ronove::admin.logs.translation_saved',
            ],
            'ronove.translations.deleted' => [
                'icon' => 'trash',
                'color' => 'danger',
                'message' => 'ronove::admin.logs.translation_deleted',
            ],
        ]);
    }

    private function registerLocaleMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', SetLocale::class);
        $kernel->addToMiddlewarePriorityAfter(StartSession::class, SetLocale::class);
    }

    private function registerResources(): void
    {
        $this->app->make(ResourceRegistry::class)->register(new PostResourceProvider);

        Post::deleted(function (Post $post) {
            $this->app->make(RonoveManager::class)->forget('core.post', $post);
        });
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
