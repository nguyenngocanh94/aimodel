<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplateRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AllGuidesConformanceTest extends TestCase
{
    private static function registry(): NodeTemplateRegistry
    {
        $registry = new NodeTemplateRegistry();
        foreach (glob(__DIR__ . '/../../../../app/Domain/Nodes/Templates/*Template.php') as $file) {
            $className = 'App\\Domain\\Nodes\\Templates\\' . basename($file, '.php');
            if (class_exists($className) && !((new \ReflectionClass($className))->isAbstract())) {
                $registry->register(new $className());
            }
        }
        return $registry;
    }

    public static function templateProvider(): iterable
    {
        foreach (self::registry()->all() as $type => $template) {
            yield $type => [$template];
        }
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_node_id_matches_template_type($template): void
    {
        $guide = $template->plannerGuide();
        $this->assertSame($template->type, $guide->nodeId);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_purpose_is_not_empty($template): void
    {
        $guide = $template->plannerGuide();
        $this->assertNotEmpty($guide->purpose);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_serializes_to_array($template): void
    {
        $guide = $template->plannerGuide();
        $arr = $guide->toArray();

        $this->assertArrayHasKey('node_id', $arr);
        $this->assertArrayHasKey('purpose', $arr);
        $this->assertArrayHasKey('vibe_impact', $arr);
        $this->assertArrayHasKey('human_gate', $arr);
        $this->assertArrayHasKey('knobs', $arr);
        $this->assertArrayHasKey('connects_to', $arr);
        $this->assertArrayHasKey('when_to_include', $arr);
        $this->assertArrayHasKey('when_to_skip', $arr);
        $this->assertContains($arr['vibe_impact'], ['critical', 'neutral']);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_serializes_to_yaml($template): void
    {
        $guide = $template->plannerGuide();
        $yaml = $guide->toYaml();

        $this->assertIsString($yaml);
        $this->assertStringContainsString($guide->nodeId, $yaml);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_knobs_have_required_fields($template): void
    {
        $guide = $template->plannerGuide();

        foreach ($guide->knobs as $knob) {
            $arr = $knob->toArray();
            $this->assertArrayHasKey('name', $arr, "Knob missing name in {$guide->nodeId}");
            $this->assertArrayHasKey('type', $arr, "Knob missing type in {$guide->nodeId}");
            $this->assertArrayHasKey('default', $arr, "Knob missing default in {$guide->nodeId}");
            $this->assertArrayHasKey('effect', $arr, "Knob missing effect in {$guide->nodeId}");
        }
    }
}
