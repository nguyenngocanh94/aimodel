<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner\Tools;

use App\Domain\Planner\Tools\SchemaValidationTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SchemaValidationToolTest extends TestCase
{
    #[Test]
    public function empty_input_reports_invalid(): void
    {
        $tool = $this->app->make(SchemaValidationTool::class);

        $result = json_decode($tool->handle(new Request(['plan_json' => ''])), true);

        $this->assertFalse($result['valid']);
        $this->assertSame('empty_input', $result['errors'][0]['code']);
    }

    #[Test]
    public function unparseable_json_reports_parse_error(): void
    {
        $tool = $this->app->make(SchemaValidationTool::class);

        $result = json_decode(
            $tool->handle(new Request(['plan_json' => 'not-even-json{{{'])),
            true,
        );

        $this->assertFalse($result['valid']);
        $this->assertSame('parse_error', $result['errors'][0]['code']);
    }

    #[Test]
    public function structurally_broken_plan_surfaces_validator_errors(): void
    {
        // Unknown node type — catalog has no NonExistentNodeType.
        $broken = [
            'intent' => 'broken plan',
            'vibeMode' => 'clean_education',
            'nodes' => [
                [
                    'id' => 'n1',
                    'type' => 'NonExistentNodeType',
                    'config' => [],
                    'reason' => 'test',
                    'label' => null,
                ],
            ],
            'edges' => [],
            'assumptions' => [],
            'rationale' => 'test',
            'meta' => ['plannerVersion' => '1.0'],
        ];

        $tool = $this->app->make(SchemaValidationTool::class);

        $result = json_decode(
            $tool->handle(new Request(['plan_json' => json_encode($broken)])),
            true,
        );

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('re-run', strtolower($result['hint']));
    }

    #[Test]
    public function description_and_schema_are_well_formed(): void
    {
        $tool = $this->app->make(SchemaValidationTool::class);

        $this->assertNotEmpty((string) $tool->description());
        $schema = $tool->schema(new JsonSchemaTypeFactory());
        $this->assertArrayHasKey('plan_json', $schema);
    }
}
