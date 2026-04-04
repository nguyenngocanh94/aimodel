<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\DataType;

final class TypeCompatibility
{
    /**
     * Scalar to list type mappings for coercion.
     * When connecting a scalar output to a list input,
     * the scalar value is wrapped in an array.
     */
    private const SCALAR_TO_LIST_MAPPINGS = [
        DataType::Text->value => DataType::TextList->value,
        DataType::Prompt->value => DataType::PromptList->value,
        DataType::Scene->value => DataType::SceneList->value,
        DataType::ImageFrame->value => DataType::ImageFrameList->value,
        DataType::ImageAsset->value => DataType::ImageAssetList->value,
    ];

    /**
     * Check if source DataType can be connected to target DataType.
     */
    public function check(DataType $source, DataType $target): CompatibilityResult
    {
        // Exact match is always compatible
        if ($source === $target) {
            return CompatibilityResult::compatible();
        }

        // Check scalar-to-list coercion
        if ($this->canCoerceScalarToList($source, $target)) {
            return CompatibilityResult::compatibleWithCoercion(
                coercionType: $target->value,
                message: "Scalar type '{$source->value}' will be wrapped as '{$target->value}'",
            );
        }

        // List-to-scalar is never allowed
        if ($this->isListType($source) && !$this->isListType($target)) {
            return CompatibilityResult::incompatible(
                reason: "Cannot connect list type '{$source->value}' to scalar type '{$target->value}'",
            );
        }

        // Everything else is incompatible
        return CompatibilityResult::incompatible(
            reason: "Type '{$source->value}' is not compatible with '{$target->value}'",
        );
    }

    /**
     * Check if a scalar type can be coerced to a list type.
     */
    private function canCoerceScalarToList(DataType $source, DataType $target): bool
    {
        $sourceValue = $source->value;
        $targetValue = $target->value;

        return isset(self::SCALAR_TO_LIST_MAPPINGS[$sourceValue])
            && self::SCALAR_TO_LIST_MAPPINGS[$sourceValue] === $targetValue;
    }

    /**
     * Check if a DataType is a list type.
     */
    private function isListType(DataType $type): bool
    {
        return str_ends_with($type->value, 'List');
    }

    /**
     * Get all scalar types that can be coerced.
     */
    public static function getCoercibleScalarTypes(): array
    {
        return array_map(
            fn (string $value) => DataType::from($value),
            array_keys(self::SCALAR_TO_LIST_MAPPINGS)
        );
    }

    /**
     * Get the list type that a scalar can be coerced to.
     */
    public static function getCoercedListType(DataType $scalar): ?DataType
    {
        $mapping = self::SCALAR_TO_LIST_MAPPINGS[$scalar->value] ?? null;
        return $mapping ? DataType::from($mapping) : null;
    }
}
