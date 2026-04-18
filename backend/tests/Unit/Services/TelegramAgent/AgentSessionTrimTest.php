<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Services\TelegramAgent\AgentSession;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AgentSession::trimMessages — in particular the rule
 * that a trim must never leave a tool_result user message at the head of the
 * kept window (OpenAI-compatible providers reject such input).
 */
final class AgentSessionTrimTest extends TestCase
{
    private function userText(string $t): array
    {
        return ['role' => 'user', 'content' => $t];
    }

    private function assistantToolUse(string $callId, string $name = 'list_workflows'): array
    {
        return ['role' => 'assistant', 'content' => [
            ['type' => 'tool_use', 'id' => $callId, 'name' => $name, 'input' => []],
        ]];
    }

    private function userToolResult(string $callId): array
    {
        return ['role' => 'user', 'content' => [
            ['type' => 'tool_result', 'tool_use_id' => $callId, 'content' => '{}'],
        ]];
    }

    private function assistantText(string $t): array
    {
        return ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => $t]]];
    }

    #[Test]
    public function trim_noop_when_below_threshold(): void
    {
        $session = new AgentSession('c', 'b', [
            $this->userText('hi'),
            $this->assistantText('hello'),
        ]);

        $session->trimMessages(10);

        $this->assertCount(2, $session->messages);
    }

    #[Test]
    public function trim_drops_leading_orphan_tool_result(): void
    {
        // Build a 4-pair history (8 messages): user/assistant-tool-use/user-tool-result/assistant-text.
        // Trimming to 3 would slice mid-pair and leave a tool_result at head.
        $messages = [];
        for ($i = 1; $i <= 4; $i++) {
            $messages[] = $this->userText("question {$i}");
            $messages[] = $this->assistantToolUse("call_{$i}");
            $messages[] = $this->userToolResult("call_{$i}");
            $messages[] = $this->assistantText("answer {$i}");
        }

        $session = new AgentSession('c', 'b', $messages);
        $session->trimMessages(3);

        $this->assertNotEmpty($session->messages);
        $head = $session->messages[0];

        // The first kept message must NOT be a user-with-tool_result orphan.
        $isOrphan = $head['role'] === 'user'
            && is_array($head['content'])
            && ($head['content'][0]['type'] ?? null) === 'tool_result';

        $this->assertFalse($isOrphan, 'Trim must not leave a tool_result user message at the head');
    }

    #[Test]
    public function trim_preserves_plain_user_text_at_head(): void
    {
        $messages = [
            $this->userText('very old 1'),
            $this->assistantText('reply 1'),
            $this->userText('newer'),
            $this->assistantText('reply 2'),
        ];

        $session = new AgentSession('c', 'b', $messages);
        $session->trimMessages(2);

        $this->assertCount(2, $session->messages);
        $this->assertSame('user', $session->messages[0]['role']);
        $this->assertSame('newer', $session->messages[0]['content']);
    }

    #[Test]
    public function trim_drops_multiple_consecutive_orphans_after_slice(): void
    {
        // Craft a history where tail-slicing to `$max` leaves two consecutive
        // orphan tool_results at the head. Both must be dropped.
        $messages = [
            $this->userText('old user'),
            $this->assistantText('old reply'),
            $this->userToolResult('dangling_1'),
            $this->userToolResult('dangling_2'),
            $this->assistantText('fresh reply'),
            $this->userText('new user turn'),
        ];

        $session = new AgentSession('c', 'b', $messages);
        $session->trimMessages(4); // tail-slice keeps indexes 2..5; head = orphan

        $this->assertNotEmpty($session->messages);
        $head = $session->messages[0];
        $isOrphan = $head['role'] === 'user'
            && is_array($head['content'])
            && ($head['content'][0]['type'] ?? null) === 'tool_result';
        $this->assertFalse($isOrphan, 'Both leading orphans should be dropped');
    }
}
