<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\NodeRunStatus;
use App\Domain\RunStatus;
use App\Domain\RunTrigger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

final class DomainEnumTest extends TestCase
{
    #[Test]
    #[DataProvider('dataTypeCases')]
    public function data_type_can_be_constructed_from_string(string $value, DataType $expected): void
    {
        $this->assertSame($expected, DataType::from($value));
    }

    public static function dataTypeCases(): iterable
    {
        yield ['text', DataType::Text];
        yield ['textList', DataType::TextList];
        yield ['prompt', DataType::Prompt];
        yield ['promptList', DataType::PromptList];
        yield ['script', DataType::Script];
        yield ['scene', DataType::Scene];
        yield ['sceneList', DataType::SceneList];
        yield ['imageFrame', DataType::ImageFrame];
        yield ['imageFrameList', DataType::ImageFrameList];
        yield ['imageAsset', DataType::ImageAsset];
        yield ['imageAssetList', DataType::ImageAssetList];
        yield ['audioPlan', DataType::AudioPlan];
        yield ['audioAsset', DataType::AudioAsset];
        yield ['subtitleAsset', DataType::SubtitleAsset];
        yield ['videoAsset', DataType::VideoAsset];
        yield ['reviewDecision', DataType::ReviewDecision];
        yield ['json', DataType::Json];
    }

    #[Test]
    public function data_type_has_17_cases(): void
    {
        $this->assertCount(17, DataType::cases());
    }

    #[Test]
    public function data_type_rejects_invalid_string(): void
    {
        $this->expectException(ValueError::class);
        DataType::from('invalid');
    }

    #[Test]
    #[DataProvider('nodeCategoryCases')]
    public function node_category_can_be_constructed_from_string(string $value, NodeCategory $expected): void
    {
        $this->assertSame($expected, NodeCategory::from($value));
    }

    public static function nodeCategoryCases(): iterable
    {
        yield ['input', NodeCategory::Input];
        yield ['script', NodeCategory::Script];
        yield ['visuals', NodeCategory::Visuals];
        yield ['audio', NodeCategory::Audio];
        yield ['video', NodeCategory::Video];
        yield ['utility', NodeCategory::Utility];
        yield ['output', NodeCategory::Output];
    }

    #[Test]
    public function node_category_has_7_cases(): void
    {
        $this->assertCount(7, NodeCategory::cases());
    }

    #[Test]
    public function node_category_rejects_invalid_string(): void
    {
        $this->expectException(ValueError::class);
        NodeCategory::from('invalid');
    }

    #[Test]
    #[DataProvider('runStatusCases')]
    public function run_status_can_be_constructed_from_string(string $value, RunStatus $expected): void
    {
        $this->assertSame($expected, RunStatus::from($value));
    }

    public static function runStatusCases(): iterable
    {
        yield ['pending', RunStatus::Pending];
        yield ['running', RunStatus::Running];
        yield ['awaitingReview', RunStatus::AwaitingReview];
        yield ['success', RunStatus::Success];
        yield ['error', RunStatus::Error];
        yield ['cancelled', RunStatus::Cancelled];
        yield ['interrupted', RunStatus::Interrupted];
    }

    #[Test]
    public function run_status_has_7_cases(): void
    {
        $this->assertCount(7, RunStatus::cases());
    }

    #[Test]
    public function run_status_rejects_invalid_string(): void
    {
        $this->expectException(ValueError::class);
        RunStatus::from('invalid');
    }

    #[Test]
    #[DataProvider('nodeRunStatusCases')]
    public function node_run_status_can_be_constructed_from_string(string $value, NodeRunStatus $expected): void
    {
        $this->assertSame($expected, NodeRunStatus::from($value));
    }

    public static function nodeRunStatusCases(): iterable
    {
        yield ['pending', NodeRunStatus::Pending];
        yield ['running', NodeRunStatus::Running];
        yield ['awaitingReview', NodeRunStatus::AwaitingReview];
        yield ['success', NodeRunStatus::Success];
        yield ['error', NodeRunStatus::Error];
        yield ['skipped', NodeRunStatus::Skipped];
        yield ['cancelled', NodeRunStatus::Cancelled];
    }

    #[Test]
    public function node_run_status_has_7_cases(): void
    {
        $this->assertCount(7, NodeRunStatus::cases());
    }

    #[Test]
    public function node_run_status_rejects_invalid_string(): void
    {
        $this->expectException(ValueError::class);
        NodeRunStatus::from('invalid');
    }

    #[Test]
    #[DataProvider('runTriggerCases')]
    public function run_trigger_can_be_constructed_from_string(string $value, RunTrigger $expected): void
    {
        $this->assertSame($expected, RunTrigger::from($value));
    }

    public static function runTriggerCases(): iterable
    {
        yield ['runWorkflow', RunTrigger::RunWorkflow];
        yield ['runNode', RunTrigger::RunNode];
        yield ['runFromHere', RunTrigger::RunFromHere];
        yield ['runUpToHere', RunTrigger::RunUpToHere];
    }

    #[Test]
    public function run_trigger_has_4_cases(): void
    {
        $this->assertCount(4, RunTrigger::cases());
    }

    #[Test]
    public function run_trigger_rejects_invalid_string(): void
    {
        $this->expectException(ValueError::class);
        RunTrigger::from('invalid');
    }

    #[Test]
    #[DataProvider('capabilityCases')]
    public function capability_can_be_constructed_from_string(string $value, Capability $expected): void
    {
        $this->assertSame($expected, Capability::from($value));
    }

    public static function capabilityCases(): iterable
    {
        yield ['text_generation', Capability::TextGeneration];
        yield ['text_to_image', Capability::TextToImage];
        yield ['text_to_speech', Capability::TextToSpeech];
        yield ['structured_transform', Capability::StructuredTransform];
        yield ['media_composition', Capability::MediaComposition];
    }

    #[Test]
    public function capability_has_5_cases(): void
    {
        $this->assertCount(5, Capability::cases());
    }

    #[Test]
    public function capability_rejects_invalid_string(): void
    {
        $this->expectException(ValueError::class);
        Capability::from('invalid');
    }
}
