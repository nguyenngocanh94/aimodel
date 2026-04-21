<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\TelegramAgent\RedisConversationStore;
use App\Services\TelegramAgent\BehaviorSkills\BehaviorSkillComposer;
use App\Services\TelegramAgent\Tools\ComposeWorkflowTool;
use App\Services\TelegramAgent\Tools\PersistWorkflowTool;
use App\Services\TelegramAgent\Tools\RefinePlanTool;
use App\Services\TelegramAgent\Tools\ReplyTool;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Contracts\ConversationStore;

/**
 * @property-read string $chatId
 * @property-read string $botToken
 */
class TelegramAgentContext
{
    public function __construct(
        public string $chatId = 'test-chat-id',
        public string $botToken = 'test-bot-token',
    ) {}
}

class TelegramAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind our Redis-backed ConversationStore so RemembersConversations works.
        $this->app->singleton(ConversationStore::class, RedisConversationStore::class);
        $this->app->singleton(RedisConversationStore::class, RedisConversationStore::class);

        $this->app->singleton(BehaviorSkillComposer::class, BehaviorSkillComposer::class);

        // Request-scoped context holding per-request Telegram identity.
        // Set by the controller before constructing TelegramAgent.
        $this->app->scoped(TelegramAgentContext::class);

        // Factory bindings for Telegram tools that need per-request chatId/botToken.
        // TelegramAgent::getSkillToolOverrides() constructs these directly with
        // the correct per-request values; these bindings serve test / container-resolve paths.
        $this->app->bind(ReplyTool::class, function ($app) {
            $ctx = $app->make(TelegramAgentContext::class);

            return new ReplyTool(botToken: $ctx->botToken, chatId: $ctx->chatId);
        });

        $this->app->bind(RunWorkflowTool::class, function ($app) {
            $ctx = $app->make(TelegramAgentContext::class);

            return new RunWorkflowTool(chatId: $ctx->chatId);
        });

        $this->app->bind(ComposeWorkflowTool::class, function ($app) {
            $ctx = $app->make(TelegramAgentContext::class);

            return new ComposeWorkflowTool(
                planner: $app->make(\App\Domain\Planner\WorkflowPlanner::class),
                sessionStore: $app->make(\App\Services\TelegramAgent\AgentSessionStore::class),
                chatId: $ctx->chatId,
                botToken: $ctx->botToken,
            );
        });

        $this->app->bind(RefinePlanTool::class, function ($app) {
            $ctx = $app->make(TelegramAgentContext::class);

            return new RefinePlanTool(
                planner: $app->make(\App\Domain\Planner\WorkflowPlanner::class),
                manifestBuilder: $app->make(\App\Domain\Nodes\NodeManifestBuilder::class),
                registry: $app->make(\App\Domain\Nodes\NodeTemplateRegistry::class),
                validator: $app->make(\App\Domain\Planner\WorkflowPlanValidator::class),
                sessionStore: $app->make(\App\Services\TelegramAgent\AgentSessionStore::class),
                chatId: $ctx->chatId,
                botToken: $ctx->botToken,
            );
        });

        $this->app->bind(PersistWorkflowTool::class, function ($app) {
            $ctx = $app->make(TelegramAgentContext::class);

            return new PersistWorkflowTool(
                converter: $app->make(\App\Domain\Planner\WorkflowPlanToDocumentConverter::class),
                sessionStore: $app->make(\App\Services\TelegramAgent\AgentSessionStore::class),
                chatId: $ctx->chatId,
                botToken: $ctx->botToken,
            );
        });
    }
}
