<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SitemapRegistry
{
    protected Collection $collectors;

    public function __construct()
    {
        $this->collectors = collect();
    }

    /**
     * Register a callback that returns an array of URLs
     */
    public function addCollector(callable $collector): void
    {
        $this->collectors->push($collector);
    }

    /**
     * Get all registered URLs from all sources
     */
    public function getAllUrls(): Collection
    {
        return $this->collectors->flatMap(fn($collector) => $collector());
    }
}
