<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Anthropic;

use App\Services\Anthropic\AnthropicToolUseClient;
use App\Services\Anthropic\ToolDefinition;
use App\Services\Anthropic\ToolUseResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AnthropicToolUseClientTest extends TestCase
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private function makeClient(): AnthropicToolUseClient
    {
        return new AnthropicToolUseClient(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-6',
            maxTokens: 1024,
        );
    }

    private function makeTool(string $name = 'list_workflows'): ToolDefinition
    {
        return new ToolDefinition(
            name: $name,
            description: 'List available workflows',
            inputSchema: [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ],
        );
    }

    /** Happy path: plain text end_turn response */
    public function test_text_end_turn_response(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'id' => 'msg_001',
                'type' => 'message',
                'role' => 'assistant',
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello, I can help you.'],
                ],
            ], 200),
        ]);

        $client = $this->makeClient();
        $result = $client->send(
            messages: [['role' => 'user', 'content' => 'Hi']],
            systemPrompt: 'You are a helpful assistant.',
            tools: [],
        );

        $this->assertInstanceOf(ToolUseResult::class, $result);
        $this->assertSame('end_turn', $result->stopReason);
        $this->assertSame(['Hello, I can help you.'], $result->textBlocks);
        $this->assertSame([], $result->toolCalls);
        $this->assertFalse($result->hasToolCalls());
    }

    /** Tool-use path: assistant returns a tool_use block */
    public function test_tool_use_response(): void
    {
        $rawContent = [
            [
                'type' => 'tool_use',
                'id' => 'toolu_01abc',
                'name' => 'list_workflows',
                'input' => [],
            ],
        ];

        Http::fake([
            self::API_URL => Http::response([
                'id' => 'msg_002',
                'type' => 'message',
                'role' => 'assistant',
                'stop_reason' => 'tool_use',
                'content' => $rawContent,
            ], 200),
        ]);

        $client = $this->makeClient();
        $result = $client->send(
            messages: [['role' => 'user', 'content' => 'List workflows']],
            systemPrompt: 'You are an agent.',
            tools: [$this->makeTool()],
        );

        $this->assertSame('tool_use', $result->stopReason);
        $this->assertTrue($result->hasToolCalls());
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('toolu_01abc', $result->toolCalls[0]['id']);
        $this->assertSame('list_workflows', $result->toolCalls[0]['name']);
        $this->assertSame([], $result->toolCalls[0]['input']);
        $this->assertSame([], $result->textBlocks);

        // rawAssistantMessage must be the full content block list
        $this->assertSame($rawContent, $result->rawAssistantMessage);
    }

    /** Mixed response: text and tool_use in one reply */
    public function test_mixed_text_and_tool_use_response(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'id' => 'msg_003',
                'type' => 'message',
                'role' => 'assistant',
                'stop_reason' => 'tool_use',
                'content' => [
                    ['type' => 'text', 'text' => 'Let me check the workflows.'],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_02def',
                        'name' => 'list_workflows',
                        'input' => [],
                    ],
                ],
            ], 200),
        ]);

        $client = $this->makeClient();
        $result = $client->send(
            messages: [['role' => 'user', 'content' => 'What can you do?']],
            systemPrompt: 'You are an agent.',
            tools: [$this->makeTool()],
        );

        $this->assertSame('tool_use', $result->stopReason);
        $this->assertSame(['Let me check the workflows.'], $result->textBlocks);
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('toolu_02def', $result->toolCalls[0]['id']);
    }

    /** Request shape: tools key present with input_schema; system field sent */
    public function test_request_shape_with_tools(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'id' => 'msg_004',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'OK']],
            ], 200),
        ]);

        $tool = new ToolDefinition(
            name: 'run_workflow',
            description: 'Run a workflow',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string'],
                ],
                'required' => ['slug'],
            ],
        );

        $client = $this->makeClient();
        $client->send(
            messages: [['role' => 'user', 'content' => 'Run it']],
            systemPrompt: 'System prompt here.',
            tools: [$tool],
        );

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($tool): bool {
            $body = $request->data();

            // system field present
            if (($body['system'] ?? '') !== 'System prompt here.') {
                return false;
            }

            // tools key present and non-empty
            if (! isset($body['tools']) || $body['tools'] === []) {
                return false;
            }

            $serialized = $body['tools'][0];

            // Anthropic API expects input_schema, not inputSchema
            return isset($serialized['input_schema'])
                && $serialized['name'] === 'run_workflow'
                && $serialized['description'] === 'Run a workflow';
        });
    }

    /** No-tools: when $tools=[] the request body has no 'tools' key */
    public function test_no_tools_key_when_tools_empty(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'id' => 'msg_005',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'Sure']],
            ], 200),
        ]);

        $client = $this->makeClient();
        $client->send(
            messages: [['role' => 'user', 'content' => 'Hello']],
            systemPrompt: 'Be concise.',
            tools: [],
        );

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $body = $request->data();

            return ! array_key_exists('tools', $body);
        });
    }

    /** Error: non-2xx response raises RuntimeException mentioning the status code */
    public function test_non_2xx_throws_runtime_exception(): void
    {
        Http::fake([
            self::API_URL => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $client = $this->makeClient();
        $client->send(
            messages: [['role' => 'user', 'content' => 'Hi']],
            systemPrompt: 'You are helpful.',
            tools: [],
        );
    }

    /** Malformed response: missing content key throws RuntimeException */
    public function test_missing_content_throws_runtime_exception(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'id' => 'msg_006',
                'stop_reason' => 'end_turn',
                // 'content' intentionally omitted
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/malformed|content/i');

        $client = $this->makeClient();
        $client->send(
            messages: [['role' => 'user', 'content' => 'Hi']],
            systemPrompt: 'You are helpful.',
            tools: [],
        );
    }

    /** ToolDefinition::toArray produces the correct shape */
    public function test_tool_definition_to_array(): void
    {
        $tool = new ToolDefinition(
            name: 'my_tool',
            description: 'Does something useful',
            inputSchema: ['type' => 'object', 'properties' => []],
        );

        $arr = $tool->toArray();

        $this->assertSame('my_tool', $arr['name']);
        $this->assertSame('Does something useful', $arr['description']);
        $this->assertArrayHasKey('input_schema', $arr);
        $this->assertArrayNotHasKey('inputSchema', $arr);
        $this->assertSame(['type' => 'object', 'properties' => []], $arr['input_schema']);
    }
}
