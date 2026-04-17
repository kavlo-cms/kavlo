<?php

namespace App\Services;

class HookManager
{
    /** @var array<string, array<int, list<callable>>> */
    private array $filters = [];

    /** @var array<string, array<int, list<callable>>> */
    private array $actions = [];

    /**
     * Register a filter callback.
     * Callbacks are called in ascending priority order (lower number = earlier).
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    /**
     * Run all registered filter callbacks for the given hook and return the
     * final value.
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (empty($this->filters[$hook])) {
            return $value;
        }

        ksort($this->filters[$hook]);

        foreach ($this->filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    /**
     * Register an action callback.
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    /**
     * Fire all registered action callbacks for the given hook.
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        if (empty($this->actions[$hook])) {
            return;
        }

        ksort($this->actions[$hook]);

        foreach ($this->actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $callback(...$args);
            }
        }
    }
}
