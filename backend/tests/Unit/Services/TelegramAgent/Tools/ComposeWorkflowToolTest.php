<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Services\TelegramAgent\Tools\ComposeWorkflowTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

final class ComposeWorkflowToolTest extends TestCase
{
    public function test_description_mentions_compose_and_proposal(): void
    {
        $tool = new ComposeWorkflowTool();
        $desc = (string) $tool->description();

        $this->assertStringContainsStringIgnoringCase('compose', $desc);
        $this->assertStringContainsStringIgnoringCase('proposal', $desc);
    }

    public function test_schema_declares_required_brief_string(): void
    {
        $tool   = new ComposeWorkflowTool();
        $fields = $tool->schema(new JsonSchemaTypeFactory());

        $this->assertArrayHasKey('brief', $fields);
    }

    public function test_handle_returns_not_available_json(): void
    {
        $tool = new ComposeWorkflowTool();
        $raw  = $tool->handle(new Request(['brief' => 'test brief']));
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse($data['available']);
        $this->assertStringContainsString('aimodel-645', (string) $data['reason']);
    }
}
