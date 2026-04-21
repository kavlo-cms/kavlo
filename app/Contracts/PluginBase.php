<?php

namespace App\Contracts;

use App\Plugins\PluginContext;

interface PluginBase
{
    public function boot(PluginContext $context): void;
}
