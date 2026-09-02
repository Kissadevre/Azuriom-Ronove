<?php

namespace Azuriom\Plugin\Ronove\Support;

use Illuminate\Support\Collection;

final readonly class TranslationAuditReport
{
    /**
     * @param  Collection<int, TranslationAuditIssue>  $issues
     */
    public function __construct(private Collection $issues) {}

    /**
     * @return Collection<int, TranslationAuditIssue>
     */
    public function issues(): Collection
    {
        return $this->issues;
    }

    /**
     * @return Collection<int, TranslationAuditIssue>
     */
    public function issuesFor(string $category): Collection
    {
        return $this->issues
            ->where('category', $category)
            ->values();
    }

    public function count(): int
    {
        return $this->issues->count();
    }

    public function countFor(string $category): int
    {
        return $this->issuesFor($category)->count();
    }

    public function cleanableCount(): int
    {
        return $this->issues
            ->filter(fn (TranslationAuditIssue $issue) => $issue->isCleanable())
            ->count();
    }

    public function informationalCount(): int
    {
        return $this->count() - $this->cleanableCount();
    }

    public function isHealthy(): bool
    {
        return $this->issues->isEmpty();
    }
}
