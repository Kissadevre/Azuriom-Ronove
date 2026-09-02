<?php

namespace Azuriom\Plugin\Ronove\Support;

use Azuriom\Plugin\Ronove\Models\Translation;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class TranslationCoverageReport
{
    public const MISSING = 'missing';

    public const OUTDATED = 'outdated';

    public const FILTERS = [self::MISSING, Translation::DRAFT, Translation::PUBLISHED, self::OUTDATED];

    /**
     * @param  array<string, string>  $statuses
     * @param  array<string, bool>  $outdated
     */
    public function __construct(
        private readonly array $statuses,
        private readonly array $outdated,
    ) {}

    public function total(): int
    {
        return count($this->statuses);
    }

    public function count(string $status): int
    {
        if ($status === self::OUTDATED) {
            return count(array_filter($this->outdated));
        }

        if (! in_array($status, self::FILTERS, true)) {
            throw new InvalidArgumentException("Unknown Ronove coverage status [{$status}].");
        }

        return count(array_filter($this->statuses, fn (string $value) => $value === $status));
    }

    /**
     * @return Collection<int, string>
     */
    public function keysFor(string $status): Collection
    {
        if ($status === self::OUTDATED) {
            return collect($this->outdated)
                ->filter()
                ->keys()
                ->map(fn ($key) => (string) $key)
                ->values();
        }

        if (! in_array($status, self::FILTERS, true)) {
            throw new InvalidArgumentException("Unknown Ronove coverage status [{$status}].");
        }

        return collect($this->statuses)
            ->filter(fn (string $value) => $value === $status)
            ->keys()
            ->map(fn ($key) => (string) $key)
            ->values();
    }

    public function status(string $key): string
    {
        return $this->statuses[$key] ?? self::MISSING;
    }

    public function isOutdated(string $key): bool
    {
        return $this->outdated[$key] ?? false;
    }
}
