<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Anthropic\AnthropicToolUseClient;
use App\Services\Anthropic\ToolUseClientContract;
use App\Services\Fireworks\FireworksToolUseClient;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\HandlesTelegramUpdate;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use App\Services\TelegramAgent\Tools\CancelRunTool;
use App\Services\TelegramAgent\Tools\GetRunStatusTool;
use App\Services\TelegramAgent\Tools\ListWorkflowsTool;
use App\Services\TelegramAgent\Tools\ReplyTool;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class TelegramAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolUseClientContract::class, static function (Application $app): ToolUseClientContract {
            $provider = (string) config('services.telegram_agent.provider', 'fireworks');

            return match ($provider) {
                'anthropic' => new AnthropicToolUseClient(
                    apiKey: (string) config('services.anthropic.api_key', ''),
                    model: 'claude-sonnet-4-6',
                ),
                default => new FireworksToolUseClient(
                    apiKey: (string) config('services.fireworks.api_key', ''),
                    model: (string) config('services.fireworks.model', 'accounts/fireworks/models/minimax-m2p7'),
                ),
            };
        });

        $this->app->singleton(TelegramAgent::class, static function (Application $app): TelegramAgent {
            return new TelegramAgent(
                llm: $app->make(ToolUseClientContract::class),
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

        $this->app->bind(HandlesTelegramUpdate::class, static fn ($app) => $app->make(TelegramAgent::class));
    }
}
