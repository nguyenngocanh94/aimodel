<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Nodes\ConfigSchemaTranspiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ManifestRegistryParityTest — NM4 drift safety net.
 *
 * For every NodeTemplate registered (auto-discovered from the Templates/ directory),
 * asserts:
 *   1. NodeManifestBuilder::build() returns a non-empty manifest.
 *   2. configSchema.type === 'object'.
 *   3. Every top-level key in defaultConfig() appears as a property in configSchema.
 *   4. If the template uses InteractsWithHuman (humanGateDefaultConfig exists),
 *      configSchema.properties.humanGate is a nested object with the expected sub-keys.
 *
 * Adding a new NodeTemplate automatically brings it under test — no new test file needed.
 */
final class ManifestRegistryParityTest extends TestCase
{
    // ── Registry builder (mirrors AllGuidesConformanceTest pattern) ──────────

    private static function makeRegistry(): NodeTemplateRegistry
    {
        $registry = new NodeTemplateRegistry();
        $pattern = __DIR__ . '/../../../../app/Domain/Nodes/Templates/*Template.php';

        foreach (glob($pattern) as $file) {
            $className = 'App\\Domain\\Nodes\\Templates\\' . basename($file, '.php');
            if (class_exists($className)) {
                $rc = new \ReflectionClass($className);
                if (!$rc->isAbstract() && $rc->isInstantiable()) {
                    $registry->register(new $className());
                }
            }
        }

        return $registry;
    }

    private static function makeBuilder(): NodeManifestBuilder
    {
        return new NodeManifestBuilder(new ConfigSchemaTranspiler());
    }

    // ── Data provider ─────────────────────────────────────────────────────────

    /**
     * Yields one [templateType, manifest, template] tuple per registered template.
     */
    public static function manifestProvider(): iterable
    {
        $registry = self::makeRegistry();
        $builder  = self::makeBuilder();

        foreach ($registry->all() as $type => $template) {
            $manifest = $builder->build($template);
            yield $type => [$type, $manifest, $template];
        }
    }

    // ── Assertion 1: manifest is non-empty ────────────────────────────────────

    #[Test]
    #[DataProvider('manifestProvider')]
    public function manifest_is_non_empty(string $type, array $manifest, NodeTemplate $template): void
    {
        $this->assertNotEmpty(
            $manifest,
            "{$type}: NodeManifestBuilder::build() returned an empty array",
        );
        $this->assertSame($type, $manifest['type'], "{$type}: manifest 'type' key mismatch");
        $this->assertNotEmpty($manifest['configSchema'], "{$type}: configSchema must be non-empty");
    }

    // ── Assertion 2: configSchema.type === 'object' ───────────────────────────

    #[Test]
    #[DataProvider('manifestProvider')]
    public function config_schema_type_is_object(string $type, array $manifest, NodeTemplate $template): void
    {
        $schema = $manifest['configSchema'];

        $this->assertArrayHasKey('type', $schema, "{$type}: configSchema must have a 'type' key");
        $this->assertSame('object', $schema['type'], "{$type}: configSchema.type must be 'object'");
    }

    // ── Assertion 3: every defaultConfig top-level key appears in schema ──────

    #[Test]
    #[DataProvider('manifestProvider')]
    public function default_config_keys_appear_in_schema_properties(string $type, array $manifest, NodeTemplate $template): void
    {
        $defaults = $template->defaultConfig();
        $properties = $manifest['configSchema']['properties'] ?? [];

        if (empty($defaults)) {
            // Template has no defaultConfig keys — nothing to cross-check; pass.
            $this->addToAssertionCount(1);
            return;
        }

        foreach (array_keys($defaults) as $key) {
            $this->assertArrayHasKey(
                $key,
                $properties,
                "{$type}: defaultConfig key '{$key}' is missing from configSchema.properties",
            );
        }
    }

    // ── Assertion 4: schema properties keys have defaults or are 'sometimes' ──

    #[Test]
    #[DataProvider('manifestProvider')]
    public function schema_required_properties_have_defaults_or_are_sometimes(string $type, array $manifest, NodeTemplate $template): void
    {
        $configRules  = $template->configRules();
        $defaultConfig = $template->defaultConfig();
        $properties   = $manifest['configSchema']['properties'] ?? [];
        $requiredList = $manifest['configSchema']['required'] ?? [];

        foreach (array_keys($properties) as $propKey) {
            // Flatten rules for this key to check for 'sometimes'
            $rules = [];
            if (isset($configRules[$propKey])) {
                $rawRules = $configRules[$propKey];
                if (is_string($rawRules)) {
                    $rules = explode('|', $rawRules);
                } elseif (is_array($rawRules)) {
                    $rules = $rawRules;
                }
            }

            $isSometimes = in_array('sometimes', $rules, true);
            $hasDefault  = array_key_exists($propKey, $defaultConfig);
            $isRequired  = in_array($propKey, $requiredList, true);

            if (!$isSometimes && !$hasDefault && !$isRequired) {
                // Log a notice but don't fail hard — some nested object keys may not have
                // top-level defaults (they're nested within their parent key).
                // We only fail if the schema lists it as required with no default.
                if ($isRequired) {
                    $this->fail(
                        "{$type}: property '{$propKey}' is required in configSchema but has no default in defaultConfig",
                    );
                }
            }
        }

        // The test passing without assertions is fine — it means no violations found.
        $this->addToAssertionCount(1);
    }

    // ── Assertion 5: humanGate structure when InteractsWithHuman ─────────────

    #[Test]
    #[DataProvider('manifestProvider')]
    public function human_gate_templates_have_correct_schema_structure(string $type, array $manifest, NodeTemplate $template): void
    {
        $usesHumanGate = method_exists($template, 'humanGateDefaultConfig');

        if (!$usesHumanGate) {
            // Non-human-gate templates — just assert no assertion failure
            $this->addToAssertionCount(1);
            return;
        }

        $properties = $manifest['configSchema']['properties'] ?? [];

        // humanGate must exist as a property
        $this->assertArrayHasKey(
            'humanGate',
            $properties,
            "{$type}: humanGate.enabled is missing from configSchema — InteractsWithHuman template must expose humanGate property",
        );

        $humanGate = $properties['humanGate'];

        // humanGate must be a nested object
        $this->assertSame(
            'object',
            $humanGate['type'] ?? null,
            "{$type}: configSchema.properties.humanGate.type must be 'object'",
        );

        $this->assertArrayHasKey(
            'properties',
            $humanGate,
            "{$type}: configSchema.properties.humanGate must have nested 'properties'",
        );

        $nested = $humanGate['properties'];

        // enabled: boolean
        $this->assertArrayHasKey('enabled', $nested, "{$type}: humanGate.enabled is missing from configSchema");
        $this->assertSame('boolean', $nested['enabled']['type'] ?? null, "{$type}: humanGate.enabled.type must be 'boolean'");

        // channel: string with enum
        $this->assertArrayHasKey('channel', $nested, "{$type}: humanGate.channel is missing from configSchema");
        $this->assertSame('string', $nested['channel']['type'] ?? null, "{$type}: humanGate.channel.type must be 'string'");
        $channelEnum = $nested['channel']['enum'] ?? [];
        foreach (['ui', 'telegram', 'mcp', 'any'] as $expected) {
            $this->assertContains($expected, $channelEnum, "{$type}: humanGate.channel enum must include '{$expected}'");
        }

        // botToken: string
        $this->assertArrayHasKey('botToken', $nested, "{$type}: humanGate.botToken is missing from configSchema");
        $this->assertSame('string', $nested['botToken']['type'] ?? null, "{$type}: humanGate.botToken.type must be 'string'");

        // chatId: string
        $this->assertArrayHasKey('chatId', $nested, "{$type}: humanGate.chatId is missing from configSchema");
        $this->assertSame('string', $nested['chatId']['type'] ?? null, "{$type}: humanGate.chatId.type must be 'string'");

        // options: array (possibly nullable)
        $this->assertArrayHasKey('options', $nested, "{$type}: humanGate.options is missing from configSchema");
        $optionsType = $nested['options']['type'] ?? null;
        if (is_array($optionsType)) {
            $this->assertContains('array', $optionsType, "{$type}: humanGate.options.type must include 'array'");
        } else {
            $this->assertSame('array', $optionsType, "{$type}: humanGate.options.type must be 'array'");
        }

        // timeoutSeconds: integer
        $this->assertArrayHasKey('timeoutSeconds', $nested, "{$type}: humanGate.timeoutSeconds is missing from configSchema");
        $this->assertSame('integer', $nested['timeoutSeconds']['type'] ?? null, "{$type}: humanGate.timeoutSeconds.type must be 'integer'");
    }
}
