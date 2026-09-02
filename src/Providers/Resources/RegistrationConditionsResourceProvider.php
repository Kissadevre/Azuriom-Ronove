<?php

namespace Azuriom\Plugin\Ronove\Providers\Resources;

use Azuriom\Models\Setting;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RegistrationConditionsResourceProvider extends SettingResourceProvider
{
    public function type(): string
    {
        return 'core.registration-conditions';
    }

    public function label(): string
    {
        return trans('ronove::admin.resources.registration_conditions');
    }

    public function fields(): array
    {
        return [
            'content' => TranslatableField::markdown(trans('ronove::admin.resources.fields.content')),
        ];
    }

    public function settings(): array
    {
        return [
            'conditions' => 'ronove::admin.resources.settings.registration_conditions',
        ];
    }

    public function query(): Builder
    {
        return parent::query()
            ->where('value', 'not like', 'http://%')
            ->where('value', 'not like', 'https://%');
    }

    public function find(string $key): ?Model
    {
        $setting = parent::find($key);

        return $setting instanceof Setting && ! $this->isUrl($setting->value) ? $setting : null;
    }

    private function isUrl(mixed $value): bool
    {
        return is_string($value) && preg_match('/^https?:\/\//i', $value) === 1;
    }
}
