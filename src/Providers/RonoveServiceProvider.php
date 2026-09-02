<?php

namespace Azuriom\Plugin\Ronove\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Http\Kernel;
use Azuriom\Models\ActionLog;
use Azuriom\Models\Page;
use Azuriom\Models\Permission;
use Azuriom\Models\Post;
use Azuriom\Plugin\Ronove\Middleware\SetLocale;
use Azuriom\Plugin\Ronove\Providers\Resources\FooterResourceProvider;
use Azuriom\Plugin\Ronove\Providers\Resources\PageResourceProvider;
use Azuriom\Plugin\Ronove\Providers\Resources\PostResourceProvider;
use Azuriom\Plugin\Ronove\Providers\Resources\RegistrationConditionsResourceProvider;
use Azuriom\Plugin\Ronove\Providers\Resources\SiteMessageResourceProvider;
use Azuriom\Plugin\Ronove\RonoveManager;
use Azuriom\Plugin\Ronove\Services\LanguageSwitcher;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\LocalizedSettings;
use Azuriom\Plugin\Ronove\Services\LocalizedThemeConfiguration;
use Azuriom\Plugin\Ronove\Services\PublicLanguagePage;
use Azuriom\Plugin\Ronove\Services\ResourceRegistry;
use Azuriom\Plugin\Ronove\Services\ReviewWorkflow;
use Azuriom\Plugin\Ronove\Services\ThemeTranslationManifestLoader;
use Azuriom\Plugin\Ronove\Services\ThemeTranslationRegistrar;
use Azuriom\Plugin\Ronove\Services\TranslationAudit;
use Azuriom\Plugin\Ronove\Services\TranslationCoverage;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Azuriom\Plugin\Ronove\Services\TranslationRevisionRecorder;
use Azuriom\Plugin\Ronove\View\Composers\PageTranslationComposer;
use Azuriom\Plugin\Ronove\View\Composers\PostTranslationComposer;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\View;

class RonoveServiceProvider extends BasePluginServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(LocaleManager::class);
        $this->app->singleton(LocalizedSettings::class);
        $this->app->singleton(ThemeTranslationManifestLoader::class);
        $this->app->singleton(ThemeTranslationRegistrar::class);
        $this->app->singleton(LocalizedThemeConfiguration::class);
        $this->app->singleton(LanguageSwitcher::class);
        $this->app->singleton(TranslationResolver::class);
        $this->app->singleton(TranslationCoverage::class);
        $this->app->singleton(TranslationAudit::class);
        $this->app->singleton(ReviewWorkflow::class);
        $this->app->singleton(PublicLanguagePage::class);
        $this->app->singleton(TranslationRevisionRecorder::class);
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
        $this->app->booted(fn () => $this->app->make(ThemeTranslationRegistrar::class)->registerActive());
        View::composer(['home', 'posts.index', 'posts.show'], PostTranslationComposer::class);
        View::composer('pages.show', PageTranslationComposer::class);

        Permission::registerPermissions([
            'ronove.settings' => 'ronove::admin.permissions.settings',
            'ronove.languages' => 'ronove::admin.permissions.languages',
            'ronove.translations' => 'ronove::admin.permissions.translations',
            'ronove.glossary' => 'ronove::admin.permissions.glossary',
            'ronove.publish' => 'ronove::admin.permissions.publish',
            'ronove.review' => 'ronove::admin.permissions.review',
            'ronove.audit' => 'ronove::admin.permissions.audit',
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
            'ronove.glossary.saved' => [
                'icon' => 'journal-text',
                'color' => 'success',
                'message' => 'ronove::admin.logs.glossary_saved',
            ],
            'ronove.glossary.deleted' => [
                'icon' => 'trash',
                'color' => 'danger',
                'message' => 'ronove::admin.logs.glossary_deleted',
            ],
            'ronove.notes.saved' => [
                'icon' => 'sticky',
                'color' => 'success',
                'message' => 'ronove::admin.logs.note_saved',
            ],
            'ronove.notes.deleted' => [
                'icon' => 'trash',
                'color' => 'danger',
                'message' => 'ronove::admin.logs.note_deleted',
            ],
            'ronove.audit.cleaned' => [
                'icon' => 'shield-check',
                'color' => 'warning',
                'message' => 'ronove::admin.logs.audit_cleaned',
            ],
            'ronove.reviews.approved' => [
                'icon' => 'check-circle',
                'color' => 'success',
                'message' => 'ronove::admin.logs.review_approved',
            ],
            'ronove.reviews.changes_requested' => [
                'icon' => 'arrow-counterclockwise',
                'color' => 'warning',
                'message' => 'ronove::admin.logs.review_changes_requested',
            ],
            'ronove.revisions.restored' => [
                'icon' => 'clock-history',
                'color' => 'warning',
                'message' => 'ronove::admin.logs.revision_restored',
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
        $ronove = $this->app->make(RonoveManager::class);
        $ronove->registerIntegration(
            'core',
            'ronove::admin.integrations.core',
            'bi bi-box-seam',
            order: 0,
        );
        $ronove->registerResourceType(new PostResourceProvider, 'core');
        $ronove->registerResourceType(new PageResourceProvider, 'core');
        $ronove->registerResourceType(new SiteMessageResourceProvider, 'core');
        $ronove->registerResourceType(new RegistrationConditionsResourceProvider, 'core');
        $ronove->registerResourceType(new FooterResourceProvider, 'core');

        Post::deleted(function (Post $post) {
            $this->app->make(RonoveManager::class)->forget('core.post', $post);
        });

        Page::deleted(function (Page $page) {
            $this->app->make(RonoveManager::class)->forget('core.page', $page);
        });
    }

    protected function routeDescriptions(): array
    {
        return [];
    }

    protected function adminNavigation(): array
    {
        return [
            'ronove' => [
                'name' => trans('ronove::admin.title'),
                'type' => 'dropdown',
                'icon' => 'bi bi-translate',
                'permission' => ['ronove.settings', 'ronove.languages', 'ronove.translations', 'ronove.glossary', 'ronove.audit'],
                'route' => 'ronove.admin.*',
                'items' => [
                    'ronove.admin.settings.index' => [
                        'name' => trans('ronove::admin.nav.settings'),
                        'permission' => 'ronove.settings',
                    ],
                    'ronove.admin.languages.index' => [
                        'name' => trans('ronove::admin.nav.languages'),
                        'permission' => 'ronove.languages',
                    ],
                    'ronove.admin.translations.index' => [
                        'name' => trans('ronove::admin.nav.translations'),
                        'permission' => 'ronove.translations',
                    ],
                    'ronove.admin.glossary.index' => [
                        'name' => trans('ronove::admin.nav.glossary'),
                        'permission' => 'ronove.glossary',
                    ],
                    'ronove.admin.audit.index' => [
                        'name' => trans('ronove::admin.nav.audit'),
                        'permission' => 'ronove.audit',
                    ],
                ],
            ],
        ];
    }
}
