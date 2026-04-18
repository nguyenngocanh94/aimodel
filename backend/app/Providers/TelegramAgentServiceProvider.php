<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Anthropic\AnthropicToolUseClient;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\HandlesTelegramUpdate;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use App\Services\TelegramAgent\Tools\CancelRunTool;
use App\Services\TelegramAgent\Tools\GetRunStatusTool;
use App\Services\TelegramAgent\Tools\ListWorkflowsTool;
use App\Services\TelegramAgent\Tools\ReplyTool;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Illuminate\Support\ServiceProvider;

class TelegramAgentServiceProvider extends ServiceProvider
{
    /**
     * Register the TelegramAgent singleton and its dependencies.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramAgent::class, static function (): TelegramAgent {
            return new TelegramAgent(
                anthropic: new AnthropicToolUseClient(
                    apiKey: (string) config('services.anthropic.api_key', ''),
                    model: 'claude-sonnet-4-6',
                ),
                sessionStore: new AgentSessionStore(),
                slashRouter: new SlashCommandRouter(),
                tools: [
                    new ListWorkflowsTool(),
                    new RunWorkflowTool(),
                    new GetRunStatusTool(),
                    new CancelRunTool(),
                    new ReplyTool(),
                ],
            );
        });

        // Bind the interface to the singleton so the controller can be mocked in tests.
        $this->app->bind(HandlesTelegramUpdate::class, static fn ($app) => $app->make(TelegramAgent::class));
    }
}
