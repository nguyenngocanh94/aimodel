<?php

use App\Providers\AppServiceProvider;
use App\Providers\NodeTemplateServiceProvider;
use App\Providers\TelegramAgentServiceProvider;

return [
    AppServiceProvider::class,
    NodeTemplateServiceProvider::class,
    TelegramAgentServiceProvider::class,
];
