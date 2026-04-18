<?php

use App\Providers\AppServiceProvider;
use App\Providers\NodeTemplateServiceProvider;
use App\Providers\PrismServiceProvider;
use App\Providers\TelegramAgentServiceProvider;

return [
    AppServiceProvider::class,
    NodeTemplateServiceProvider::class,
    PrismServiceProvider::class,
    TelegramAgentServiceProvider::class,
];
