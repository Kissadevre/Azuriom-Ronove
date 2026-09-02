<?php

namespace Azuriom\Plugin\Ronove\View\Composers;

use Azuriom\Models\Post;
use Azuriom\Plugin\Ronove\Services\TranslationResolver;
use Illuminate\View\View;

class PostTranslationComposer
{
    public function __construct(private readonly TranslationResolver $translations) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        if (($data['post'] ?? null) instanceof Post) {
            $this->translations->overlay('core.post', [$data['post']]);
        }

        if (isset($data['posts']) && is_iterable($data['posts'])) {
            $this->translations->overlay('core.post', $data['posts']);
        }
    }
}
