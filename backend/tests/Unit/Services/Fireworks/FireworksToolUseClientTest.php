<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fireworks;

use App\Services\Anthropic\ToolDefinition;
use App\Services\Fireworks\FireworksToolUseClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class FireworksToolUseClientTest extends TestCase
{
    private function makeClient(): FireworksToolUseClient
    {
        return new FireworksToolUseClient(
            apiKey: 'fw_test_key',
            model: 'accounts/fireworks/models/minimax-m2p7',
            maxTokens: 256,
        );
    }

    #[Test]
    public function happy_path_plain_text_response(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'xin chào'],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $result = $this->makeClient()->send(
            messages: [['role' => 'user', 'content' => 'hi']],
            systemPrompt: 'you are helpful',
            tools: [],
        );

        $this->assertSame('end_turn', $result->stopReason);
        $this->assertSame(['xin chào'], $result->textBlocks);
        $this->assertSame([], $result->toolCalls);
        $this->assertSame([['type' => 'text', 'text' => 'xin chào']], $result->rawAssistantMessage);
    }

    #[Test]
    public function tool_use_response_translates_to_anthropic_shape(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_123',
                            'type' => 'function',
                            'function' => [
                                'name' => 'list_workflows',
                                'arguments' => '{}',
                            ],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ], 200),
        ]);

        $result = $this->makeClient()->send(
            messages: [['role' => 'user', 'content' => 'show me workflows']],
            systemPrompt: '',
            tools: [
                new ToolDefinition(
                    name: 'list_workflows',
                    description: 'List available workflows',
                    inputSchema: ['type' => 'object', 'properties' => (object) [], 'required' => []],
                ),
            ],
        );

        $this->assertSame('tool_use', $result->stopReason);
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('call_123', $result->toolCalls[0]['id']);
        $this->assertSame('list_workflows', $result->toolCalls[0]['name']);
        $this->assertSame([], $result->toolCalls[0]['input']);
        $this->assertSame([
            ['type' => 'tool_use', 'id' => 'call_123', 'name' => 'list_workflows', 'input' => []],
        ], $result->rawAssistantMessage);
    }

    #[Test]
    public function outbound_translates_anthropic_assistant_tool_use_to_openai_tool_calls(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'done'],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'run it'],
            ['role' => 'assistant', 'content' => [
                ['type' => 'tool_use', 'id' => 'call_abc', 'name' => 'run_workflow', 'input' => ['slug' => 'x']],
            ]],
            ['role' => 'user', 'content' => [
                ['type' => 'tool_result', 'tool_use_id' => 'call_abc', 'content' => ['runId' => '42']],
            ]],
        ];

        $this->makeClient()->send($messages, 'sys', []);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            $msgs = $body['messages'];

            if ($msgs[0]['role'] !== 'system' || $msgs[0]['content'] !== 'sys') return false;
            if ($msgs[1]['role'] !== 'user' || $msgs[1]['content'] !== 'run it') return false;

            if ($msgs[2]['role'] !== 'assistant') return false;
            if (($msgs[2]['tool_calls'][0]['function']['name'] ?? null) !== 'run_workflow') return false;
            if (($msgs[2]['tool_calls'][0]['id'] ?? null) !== 'call_abc') return false;
            $args = json_decode($msgs[2]['tool_calls'][0]['function']['arguments'], true);
            if (($args['slug'] ?? null) !== 'x') return false;

            if ($msgs[3]['role'] !== 'tool') return false;
            if (($msgs[3]['tool_call_id'] ?? null) !== 'call_abc') return false;

            $toolContent = json_decode($msgs[3]['content'], true);
            if (($toolContent['runId'] ?? null) !== '42') return false;

            return true;
        });
    }

    #[Test]
    public function tool_definitions_serialize_as_openai_functions(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            ], 200),
        ]);

        $this->makeClient()->send(
            messages: [['role' => 'user', 'content' => 'hi']],
            systemPrompt: '',
            tools: [
                new ToolDefinition(
                    name: 'ping',
                    description: 'ping tool',
                    inputSchema: ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]],
                ),
            ],
        );

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return ($body['tools'][0]['type'] ?? null) === 'function'
                && ($body['tools'][0]['function']['name'] ?? null) === 'ping'
                && ($body['tools'][0]['function']['parameters']['type'] ?? null) === 'object'
                && ($body['tool_choice'] ?? null) === 'auto';
        });
    }

    #[Test]
    public function no_tools_means_no_tools_key_in_request(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'hi'], 'finish_reason' => 'stop']],
            ], 200),
        ]);

        $this->makeClient()->send([['role' => 'user', 'content' => 'hi']], '', []);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return ! array_key_exists('tools', $body) && ! array_key_exists('tool_choice', $body);
        });
    }

    #[Test]
    public function length_finish_reason_maps_to_max_tokens(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'truncated'], 'finish_reason' => 'length']],
            ], 200),
        ]);

        $result = $this->makeClient()->send([['role' => 'user', 'content' => 'x']], '', []);

        $this->assertSame('max_tokens', $result->stopReason);
    }

    #[Test]
    public function non_2xx_response_throws_runtime_exception(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response(['error' => 'bad key'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $this->makeClient()->send([['role' => 'user', 'content' => 'hi']], '', []);
    }

    #[Test]
    public function malformed_response_throws_runtime_exception(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/choices\[0\]\.message/');

        $this->makeClient()->send([['role' => 'user', 'content' => 'hi']], '', []);
    }

    #[Test]
    public function authorization_header_is_bearer_api_key(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            ], 200),
        ]);

        $this->makeClient()->send([['role' => 'user', 'content' => 'hi']], '', []);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer fw_test_key');
        });
    }
}
