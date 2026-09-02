<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Plugin\Ronove\Support\TranslatableField;

class SiteMessageResourceProvider extends SettingResourceProvider
{
    public function type(): string
    {
        return 'core.site-message';
    }

    public function label(): string
    {
        return trans('ronove::admin.resources.site_messages');
    }

    public function fields(): array
    {
        return [
            'content' => TranslatableField::richText(trans('ronove::admin.resources.fields.content')),
        ];
    }

    public function settings(): array
    {
        return [
            'home_message' => 'ronove::admin.resources.settings.home_message',
            'welcome_alert' => 'ronove::admin.resources.settings.welcome_alert',
            'maintenance.message' => 'ronove::admin.resources.settings.maintenance_message',
        ];
    }
}
