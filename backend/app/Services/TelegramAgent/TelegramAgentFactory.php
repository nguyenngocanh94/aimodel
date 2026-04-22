<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Providers\TelegramAgentContext;
use App\Services\TelegramAgent\SlashCommandRouter;

/**
 * Thin per-request factory for {@see TelegramAgent}.
 *
 * NOT a singleton — each call to make() returns a fresh TelegramAgent
 * instance so that chatId and botToken stay isolated per webhook hit.
 *
 * Populates the request-scoped {@see TelegramAgentContext} so that closure
 * bindings in TelegramAgentServiceProvider (ComposeWorkflowTool, RefinePlanTool,
 * PersistWorkflowTool) resolve tools with the correct identity. Without this,
 * those closures would read stale/default context values since Laravel's
 * `make($abstract, [params])` does not forward overrides to closure bindings
 * whose signature omits `$parameters`.
 */
final class TelegramAgentFactory
{
    public function make(string $chatId, string $botToken): TelegramAgent
    {
        $ctx = app(TelegramAgentContext::class);
        $ctx->chatId = $chatId;
        $ctx->botToken = $botToken;

        return app()->make(TelegramAgent::class, [
            'chatId'      => $chatId,
            'botToken'    => $botToken,
            'slashRouter' => app(SlashCommandRouter::class),
        ]);
    }
}
