<?php

namespace Azuriom\Plugin\Ronove\Facades;

use Illuminate\Support\Facades\Facade;

class Ronove extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ronove';
    }
}
