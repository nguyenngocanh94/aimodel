<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\ConfigSchemaTranspiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaTranspilerTest extends TestCase
{
    private ConfigSchemaTranspiler $transpiler;

    protected function setUp(): void
    {
        $this->transpiler = new ConfigSchemaTranspiler();
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function transpile(array $rules, array $defaults = []): array
    {
        return $this->transpiler->transpile($rules, $defaults);
    }

    // ── Case 1: Empty rules ───────────────────────────────────────────────

    #[Test]
    public function empty_rules_produce_empty_object_schema(): void
    {
        $result = $this->transpile([], []);

        $this->assertSame('http://json-schema.org/draft-07/schema#', $result['$schema']);
        $this->assertSame('object', $result['type']);
        $this->assertSame([], $result['properties']);
        $this->assertSame([], $result['required']);
        $this->assertFalse($result['additionalProperties']);
    }

    // ── Case 2: Single required string ────────────────────────────────────

    #[Test]
    public function required_string_field_is_listed_in_required(): void
    {
        $result = $this->transpile([
            'name' => ['required', 'string'],
        ]);

        $this->assertSame(['string'], [$result['properties']['name']['type']]);
        $this->assertContains('name', $result['required']);
    }

    // ── Case 3: Optional (sometimes) string ──────────────────────────────

    #[Test]
    public function sometimes_string_is_not_required(): void
    {
        $result = $this->transpile([
            'description' => ['sometimes', 'string'],
        ]);

        $this->assertSame('string', $result['properties']['description']['type']);
        $this->assertNotContains('description', $result['required']);
    }

    // ── Case 4: Integer with min/max ─────────────────────────────────────

    #[Test]
    public function integer_with_min_max_uses_minimum_maximum(): void
    {
        $result = $this->transpile([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $prop = $result['properties']['count'];
        $this->assertSame('integer', $prop['type']);
        $this->assertSame(1, $prop['minimum']);
        $this->assertSame(100, $prop['maximum']);
        $this->assertArrayNotHasKey('minLength', $prop);
        $this->assertArrayNotHasKey('maxLength', $prop);
    }

    // ── Case 5: Numeric (float) ───────────────────────────────────────────

    #[Test]
    public function numeric_type_maps_to_json_schema_number(): void
    {
        $result = $this->transpile([
            'score' => ['required', 'numeric'],
        ]);

        $this->assertSame('number', $result['properties']['score']['type']);
    }

    // ── Case 6: Boolean with default ──────────────────────────────────────

    #[Test]
    public function boolean_field_with_default_embeds_default(): void
    {
        $result = $this->transpile(
            rules: ['enabled' => ['sometimes', 'boolean']],
            defaults: ['enabled' => false],
        );

        $prop = $result['properties']['enabled'];
        $this->assertSame('boolean', $prop['type']);
        $this->assertFalse($prop['default']);
    }

    // ── Case 7: Enum via in:a,b,c ─────────────────────────────────────────

    #[Test]
    public function in_rule_produces_enum_array(): void
    {
        $result = $this->transpile([
            'colour' => ['required', 'string', 'in:red,green,blue'],
        ]);

        $prop = $result['properties']['colour'];
        $this->assertSame('string', $prop['type']);
        $this->assertSame(['red', 'green', 'blue'], $prop['enum']);
    }

    // ── Case 8: Nullable string ───────────────────────────────────────────

    #[Test]
    public function nullable_string_becomes_type_union_with_null(): void
    {
        $result = $this->transpile([
            'label' => ['sometimes', 'nullable', 'string'],
        ]);

        $prop = $result['properties']['label'];
        $this->assertSame(['string', 'null'], $prop['type']);
    }

    // ── Case 9: Array type ────────────────────────────────────────────────

    #[Test]
    public function array_type_maps_correctly(): void
    {
        $result = $this->transpile([
            'tags' => ['sometimes', 'array'],
        ]);

        $this->assertSame('array', $result['properties']['tags']['type']);
    }

    // ── Case 10: Defaults embedded on multiple fields ─────────────────────

    #[Test]
    public function defaults_are_embedded_on_multiple_fields(): void
    {
        $result = $this->transpile(
            rules: [
                'provider' => ['required', 'string'],
                'model' => ['sometimes', 'string'],
                'temperature' => ['sometimes', 'numeric'],
            ],
            defaults: [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'temperature' => 0.7,
            ],
        );

        $this->assertSame('openai', $result['properties']['provider']['default']);
        $this->assertSame('gpt-4o', $result['properties']['model']['default']);
        $this->assertSame(0.7, $result['properties']['temperature']['default']);
    }

    // ── Case 11: Dot-notation — humanGate structure ───────────────────────

    #[Test]
    public function dot_notation_produces_nested_object_with_children(): void
    {
        $result = $this->transpile(
            rules: [
                'humanGate' => ['sometimes', 'array'],
                'humanGate.enabled' => ['sometimes', 'boolean'],
                'humanGate.channel' => ['sometimes', 'string', 'in:ui,telegram,mcp,any'],
            ],
            defaults: [
                'humanGate' => [
                    'enabled' => false,
                    'channel' => 'telegram',
                ],
            ],
        );

        $hg = $result['properties']['humanGate'];
        $this->assertSame('object', $hg['type']);
        $this->assertFalse($hg['additionalProperties']);

        // children
        $this->assertSame('boolean', $hg['properties']['enabled']['type']);
        $this->assertFalse($hg['properties']['enabled']['default']);

        $this->assertSame('string', $hg['properties']['channel']['type']);
        $this->assertSame(['ui', 'telegram', 'mcp', 'any'], $hg['properties']['channel']['enum']);
        $this->assertSame('telegram', $hg['properties']['channel']['default']);

        // parent is not required (it had 'sometimes')
        $this->assertNotContains('humanGate', $result['required']);
    }

    // ── Case 12: Three-level dot-notation ────────────────────────────────

    #[Test]
    public function three_level_dot_notation_nests_correctly(): void
    {
        $result = $this->transpile(
            rules: [
                'a' => ['sometimes', 'array'],
                'a.b' => ['sometimes', 'array'],
                'a.b.c' => ['required', 'string'],
            ],
            defaults: [
                'a' => [
                    'b' => [
                        'c' => 'hello',
                    ],
                ],
            ],
        );

        $a = $result['properties']['a'];
        $this->assertSame('object', $a['type']);

        $b = $a['properties']['b'];
        $this->assertSame('object', $b['type']);

        $c = $b['properties']['c'];
        $this->assertSame('string', $c['type']);
        $this->assertSame('hello', $c['default']);
        $this->assertContains('c', $b['required']);
    }

    // ── Case 13: String min/max → minLength/maxLength ────────────────────

    #[Test]
    public function string_min_max_use_minlength_maxlength(): void
    {
        $result = $this->transpile([
            'slug' => ['required', 'string', 'min:3', 'max:64'],
        ]);

        $prop = $result['properties']['slug'];
        $this->assertSame(3, $prop['minLength']);
        $this->assertSame(64, $prop['maxLength']);
        $this->assertArrayNotHasKey('minimum', $prop);
        $this->assertArrayNotHasKey('maximum', $prop);
    }

    // ── Case 14: Numeric min/max → minimum/maximum ───────────────────────

    #[Test]
    public function numeric_min_max_use_minimum_maximum(): void
    {
        $result = $this->transpile([
            'ratio' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $prop = $result['properties']['ratio'];
        $this->assertSame(0, $prop['minimum']);
        $this->assertSame(1, $prop['maximum']);
        $this->assertArrayNotHasKey('minLength', $prop);
        $this->assertArrayNotHasKey('maxLength', $prop);
    }

    // ── Case 15: Mixed — required string with min/max and default ────────

    #[Test]
    public function mixed_required_string_with_min_max_and_default(): void
    {
        $result = $this->transpile(
            rules: [
                'title' => ['required', 'string', 'min:5', 'max:100'],
            ],
            defaults: [
                'title' => 'My Workflow',
            ],
        );

        $prop = $result['properties']['title'];
        $this->assertSame('string', $prop['type']);
        $this->assertSame(5, $prop['minLength']);
        $this->assertSame(100, $prop['maxLength']);
        $this->assertSame('My Workflow', $prop['default']);
        $this->assertContains('title', $result['required']);
    }

    // ── Bonus: Nullable + array combo (humanGate.options) ────────────────

    #[Test]
    public function nullable_array_type_produces_union_with_null(): void
    {
        $result = $this->transpile([
            'options' => ['sometimes', 'nullable', 'array'],
        ]);

        $prop = $result['properties']['options'];
        $this->assertSame(['array', 'null'], $prop['type']);
    }

    // ── Bonus: Pipe-separated rule strings ───────────────────────────────

    #[Test]
    public function pipe_separated_rules_are_normalised(): void
    {
        $result = $this->transpile([
            'apiKey' => 'sometimes|string',
        ]);

        $this->assertSame('string', $result['properties']['apiKey']['type']);
        $this->assertNotContains('apiKey', $result['required']);
    }

    // ── Bonus: Full humanGate config from InteractsWithHuman ─────────────

    #[Test]
    public function full_human_gate_config_rules_transpile_correctly(): void
    {
        $rules = [
            'humanGate' => ['sometimes', 'array'],
            'humanGate.enabled' => ['sometimes', 'boolean'],
            'humanGate.channel' => ['sometimes', 'string', 'in:ui,telegram,mcp,any'],
            'humanGate.messageTemplate' => ['sometimes', 'string'],
            'humanGate.options' => ['sometimes', 'nullable', 'array'],
            'humanGate.botToken' => ['sometimes', 'string'],
            'humanGate.chatId' => ['sometimes', 'string'],
            'humanGate.timeoutSeconds' => ['sometimes', 'integer', 'min:0', 'max:86400'],
        ];

        $defaults = [
            'humanGate' => [
                'enabled' => false,
                'channel' => 'telegram',
                'messageTemplate' => '',
                'options' => ['Approve', 'Revise'],
                'botToken' => '',
                'chatId' => '',
                'timeoutSeconds' => 0,
            ],
        ];

        $result = $this->transpile($rules, $defaults);

        $hg = $result['properties']['humanGate'];
        $this->assertSame('object', $hg['type']);

        // enabled
        $this->assertSame('boolean', $hg['properties']['enabled']['type']);
        $this->assertFalse($hg['properties']['enabled']['default']);

        // channel with enum
        $this->assertSame(['ui', 'telegram', 'mcp', 'any'], $hg['properties']['channel']['enum']);
        $this->assertSame('telegram', $hg['properties']['channel']['default']);

        // timeoutSeconds min/max
        $this->assertSame(0, $hg['properties']['timeoutSeconds']['minimum']);
        $this->assertSame(86400, $hg['properties']['timeoutSeconds']['maximum']);

        // options nullable array
        $this->assertSame(['array', 'null'], $hg['properties']['options']['type']);
        $this->assertSame(['Approve', 'Revise'], $hg['properties']['options']['default']);
    }
}
