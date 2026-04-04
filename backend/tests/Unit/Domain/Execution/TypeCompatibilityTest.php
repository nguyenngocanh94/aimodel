<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use App\Domain\DataType;
use App\Domain\Execution\CompatibilityResult;
use App\Domain\Execution\TypeCompatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TypeCompatibilityTest extends TestCase
{
    private TypeCompatibility $checker;

    protected function setUp(): void
    {
        $this->checker = new TypeCompatibility();
    }

    #[Test]
    public function exact_match_is_compatible(): void
    {
        $result = $this->checker->check(DataType::Text, DataType::Text);

        $this->assertTrue($result->isCompatible());
        $this->assertFalse($result->isError());
        $this->assertFalse($result->requiresCoercion);
        $this->assertSame('ok', $result->severity);
    }

    #[Test]
    #[DataProvider('scalarToListCoercionCases')]
    public function scalar_to_list_is_compatible_with_warning(DataType $scalar, DataType $list): void
    {
        $result = $this->checker->check($scalar, $list);

        $this->assertTrue($result->isCompatible());
        $this->assertTrue($result->isWarning());
        $this->assertTrue($result->requiresCoercion);
        $this->assertSame($list->value, $result->coercionType);
    }

    public static function scalarToListCoercionCases(): iterable
    {
        yield 'text to textList' => [DataType::Text, DataType::TextList];
        yield 'prompt to promptList' => [DataType::Prompt, DataType::PromptList];
        yield 'scene to sceneList' => [DataType::Scene, DataType::SceneList];
        yield 'imageFrame to imageFrameList' => [DataType::ImageFrame, DataType::ImageFrameList];
        yield 'imageAsset to imageAssetList' => [DataType::ImageAsset, DataType::ImageAssetList];
    }

    #[Test]
    #[DataProvider('listToScalarIncompatibilityCases')]
    public function list_to_scalar_is_incompatible(DataType $list, DataType $scalar): void
    {
        $result = $this->checker->check($list, $scalar);

        $this->assertFalse($result->isCompatible());
        $this->assertTrue($result->isError());
        $this->assertSame('error', $result->severity);
        $this->assertStringContainsString('list type', $result->message);
        $this->assertStringContainsString('scalar type', $result->message);
    }

    public static function listToScalarIncompatibilityCases(): iterable
    {
        yield 'textList to text' => [DataType::TextList, DataType::Text];
        yield 'promptList to prompt' => [DataType::PromptList, DataType::Prompt];
        yield 'sceneList to scene' => [DataType::SceneList, DataType::Scene];
        yield 'imageFrameList to imageFrame' => [DataType::ImageFrameList, DataType::ImageFrame];
        yield 'imageAssetList to imageAsset' => [DataType::ImageAssetList, DataType::ImageAsset];
    }

    #[Test]
    #[DataProvider('incompatibleTypeCases')]
    public function unrelated_types_are_incompatible(DataType $source, DataType $target): void
    {
        $result = $this->checker->check($source, $target);

        $this->assertFalse($result->isCompatible());
        $this->assertTrue($result->isError());
        $this->assertSame('error', $result->severity);
    }

    public static function incompatibleTypeCases(): iterable
    {
        yield 'text to prompt' => [DataType::Text, DataType::Prompt];
        yield 'text to script' => [DataType::Text, DataType::Script];
        yield 'prompt to text' => [DataType::Prompt, DataType::Text];
        yield 'script to scene' => [DataType::Script, DataType::Scene];
        yield 'scene to imageFrame' => [DataType::Scene, DataType::ImageFrame];
        yield 'imageAsset to videoAsset' => [DataType::ImageAsset, DataType::VideoAsset];
        yield 'audioPlan to subtitleAsset' => [DataType::AudioPlan, DataType::SubtitleAsset];
    }

    #[Test]
    public function can_get_coercible_scalar_types(): void
    {
        $scalars = TypeCompatibility::getCoercibleScalarTypes();

        $this->assertContains(DataType::Text, $scalars);
        $this->assertContains(DataType::Prompt, $scalars);
        $this->assertContains(DataType::Scene, $scalars);
        $this->assertContains(DataType::ImageFrame, $scalars);
        $this->assertContains(DataType::ImageAsset, $scalars);
    }

    #[Test]
    public function can_get_coerced_list_type_for_scalar(): void
    {
        $this->assertSame(DataType::TextList, TypeCompatibility::getCoercedListType(DataType::Text));
        $this->assertSame(DataType::PromptList, TypeCompatibility::getCoercedListType(DataType::Prompt));
        $this->assertSame(DataType::SceneList, TypeCompatibility::getCoercedListType(DataType::Scene));
        $this->assertSame(DataType::ImageFrameList, TypeCompatibility::getCoercedListType(DataType::ImageFrame));
        $this->assertSame(DataType::ImageAssetList, TypeCompatibility::getCoercedListType(DataType::ImageAsset));
    }

    #[Test]
    public function non_coercible_scalar_returns_null(): void
    {
        $this->assertNull(TypeCompatibility::getCoercedListType(DataType::Script));
        $this->assertNull(TypeCompatibility::getCoercedListType(DataType::AudioPlan));
        $this->assertNull(TypeCompatibility::getCoercedListType(DataType::VideoAsset));
    }

    #[Test]
    public function compatibility_result_can_create_ok_result(): void
    {
        $result = CompatibilityResult::compatible();

        $this->assertTrue($result->isCompatible());
        $this->assertFalse($result->isError());
        $this->assertFalse($result->isWarning());
        $this->assertFalse($result->requiresCoercion);
    }

    #[Test]
    public function compatibility_result_can_create_warning_result(): void
    {
        $result = CompatibilityResult::compatibleWithCoercion('textList', 'Will wrap as list');

        $this->assertTrue($result->isCompatible());
        $this->assertTrue($result->isWarning());
        $this->assertFalse($result->isError());
        $this->assertTrue($result->requiresCoercion);
        $this->assertSame('textList', $result->coercionType);
    }

    #[Test]
    public function compatibility_result_can_create_error_result(): void
    {
        $result = CompatibilityResult::incompatible('Types do not match');

        $this->assertFalse($result->isCompatible());
        $this->assertTrue($result->isError());
        $this->assertFalse($result->isWarning());
        $this->assertSame('error', $result->severity);
        $this->assertSame('Types do not match', $result->message);
    }

    #[Test]
    public function compatibility_result_throws_on_invalid_severity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Severity must be 'ok', 'warning', or 'error'");

        new CompatibilityResult(
            compatible: true,
            severity: 'invalid',
        );
    }

    #[Test]
    public function compatibility_result_can_round_trip_through_array(): void
    {
        $original = CompatibilityResult::compatibleWithCoercion('textList', 'Wrap it');

        $array = $original->toArray();

        $this->assertTrue($array['compatible']);
        $this->assertSame('warning', $array['severity']);
        $this->assertSame('Wrap it', $array['message']);
        $this->assertTrue($array['requiresCoercion']);
        $this->assertSame('textList', $array['coercionType']);
    }
}
