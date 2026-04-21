<?php

use App\Providers\AppServiceProvider;
use App\Providers\NodeTemplateServiceProvider;
use App\Providers\SkillsServiceProvider;
use App\Providers\TelegramAgentServiceProvider;

return [
    AppServiceProvider::class,
    NodeTemplateServiceProvider::class,
    SkillsServiceProvider::class,
    TelegramAgentServiceProvider::class,
];
