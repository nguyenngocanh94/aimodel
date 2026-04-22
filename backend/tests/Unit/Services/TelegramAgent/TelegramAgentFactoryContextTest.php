<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Providers\TelegramAgentContext;
use App\Services\TelegramAgent\TelegramAgentFactory;
use App\Services\TelegramAgent\Tools\ComposeWorkflowTool;
use App\Services\TelegramAgent\Tools\PersistWorkflowTool;
use App\Services\TelegramAgent\Tools\RefinePlanTool;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regression coverage for a bug where TelegramAgentContext was never populated
 * with the real per-request chatId/botToken. Laravel's `make($abstract, [params])`
 * does not forward override parameters to closure bindings whose signature omits
 * the `$parameters` argument, so ComposeWorkflowTool / RefinePlanTool /
 * PersistWorkflowTool would silently resolve with the scoped default
 * ('test-chat-id' / 'test-bot-token'), collapsing every user's draft state into
 * one shared AgentSessionStore key.
 */
final class TelegramAgentFactoryContextTest extends TestCase
{
    #[Test]
    public function factory_populates_context_with_real_identity(): void
    {
        $factory = $this->app->make(TelegramAgentFactory::class);
        $factory->make(chatId: 'real-chat-123', botToken: 'real-bot-abc');

        $ctx = $this->app->make(TelegramAgentContext::class);

        $this->assertSame('real-chat-123', $ctx->chatId);
        $this->assertSame('real-bot-abc', $ctx->botToken);
    }

    #[Test]
    public function compose_workflow_tool_resolves_with_real_identity(): void
    {
        $factory = $this->app->make(TelegramAgentFactory::class);
        $factory->make(chatId: 'real-chat-123', botToken: 'real-bot-abc');

        $tool = $this->app->make(ComposeWorkflowTool::class);

        $this->assertSame('real-chat-123', $this->readPrivateProperty($tool, 'chatId'));
        $this->assertSame('real-bot-abc', $this->readPrivateProperty($tool, 'botToken'));
    }

    #[Test]
    public function refine_plan_tool_resolves_with_real_identity(): void
    {
        $factory = $this->app->make(TelegramAgentFactory::class);
        $factory->make(chatId: 'real-chat-123', botToken: 'real-bot-abc');

        $tool = $this->app->make(RefinePlanTool::class);

        $this->assertSame('real-chat-123', $this->readPrivateProperty($tool, 'chatId'));
        $this->assertSame('real-bot-abc', $this->readPrivateProperty($tool, 'botToken'));
    }

    #[Test]
    public function persist_workflow_tool_resolves_with_real_identity(): void
    {
        $factory = $this->app->make(TelegramAgentFactory::class);
        $factory->make(chatId: 'real-chat-123', botToken: 'real-bot-abc');

        $tool = $this->app->make(PersistWorkflowTool::class);

        $this->assertSame('real-chat-123', $this->readPrivateProperty($tool, 'chatId'));
        $this->assertSame('real-bot-abc', $this->readPrivateProperty($tool, 'botToken'));
    }

    #[Test]
    public function second_make_call_updates_context(): void
    {
        $factory = $this->app->make(TelegramAgentFactory::class);

        $factory->make(chatId: 'first-chat', botToken: 'first-bot');
        $factory->make(chatId: 'second-chat', botToken: 'second-bot');

        $ctx = $this->app->make(TelegramAgentContext::class);
        $this->assertSame('second-chat', $ctx->chatId);
        $this->assertSame('second-bot', $ctx->botToken);
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }
}
