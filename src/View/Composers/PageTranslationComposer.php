<?php

namespace Azuriom\Plugin\Ronove\View\Composers;

use Azuriom\Models\Page;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Illuminate\View\View;

class PageTranslationComposer
{
    public function __construct(private readonly TranslationResolver $translations) {}

    public function compose(View $view): void
    {
        $page = $view->getData()['page'] ?? null;

        if ($page instanceof Page) {
            $this->translations->overlay('core.page', [$page]);
        }
    }
}
