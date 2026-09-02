<?php

namespace Azuriom\Plugin\Ronove\Support;

use JsonSerializable;

final class LocaleOption implements JsonSerializable
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $nativeName,
        public readonly bool $isCurrent,
        public readonly ?string $flagCode = null,
    ) {}

    /**
     * @return array{code: string, name: string, native_name: string, is_current: bool, flag_code: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->nativeName,
            'is_current' => $this->isCurrent,
            'flag_code' => $this->flagCode,
        ];
    }
}
