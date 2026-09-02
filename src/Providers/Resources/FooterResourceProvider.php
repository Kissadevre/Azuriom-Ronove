<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Plugin\Ronove\Support\TranslatableField;

class FooterResourceProvider extends SettingResourceProvider
{
    public function type(): string
    {
        return 'core.footer';
    }

    public function label(): string
    {
        return trans('ronove::admin.resources.footer');
    }

    public function fields(): array
    {
        return [
            'content' => TranslatableField::text(trans('ronove::admin.resources.fields.content'), 150),
        ];
    }

    public function settings(): array
    {
        return [
            'copyright' => 'ronove::admin.resources.settings.copyright',
        ];
    }
}
