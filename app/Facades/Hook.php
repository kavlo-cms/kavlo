<?php

namespace App\Facades;

use App\Services\HookManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void  addFilter(string $hook, callable $callback, int $priority = 10)
 * @method static mixed applyFilters(string $hook, mixed $value, mixed ...$args)
 * @method static void  addAction(string $hook, callable $callback, int $priority = 10)
 * @method static void  doAction(string $hook, mixed ...$args)
 *
 * @see \App\Services\HookManager
 */
class Hook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HookManager::class;
    }
}
