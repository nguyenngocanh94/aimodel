<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wan;

use App\Domain\Wan\PromptDictionary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PromptDictionaryTest extends TestCase
{
    // ──────────────────────────────────────────────
    //  Category arrays: non-empty + all strings + no duplicates
    // ──────────────────────────────────────────────

    /**
     * @return iterable<string, array{string}>
     */
    public static function categoryProvider(): iterable
    {
        foreach (PromptDictionary::categories() as $label => $method) {
            yield $label => [$method];
        }
    }

    #[Test]
    #[DataProvider('categoryProvider')]
    public function category_returns_non_empty_array_of_strings(string $method): void
    {
        $terms = PromptDictionary::$method();

        $this->assertIsArray($terms);
        $this->assertNotEmpty($terms, "{$method}() must not be empty");

        foreach ($terms as $term) {
            $this->assertIsString($term, "{$method}() must contain only strings");
            $this->assertNotEmpty(trim($term), "{$method}() must not contain blank strings");
        }
    }

    #[Test]
    #[DataProvider('categoryProvider')]
    public function category_has_no_duplicate_terms(string $method): void
    {
        $terms = PromptDictionary::$method();
        $unique = array_unique($terms);

        $this->assertCount(
            count($unique),
            $terms,
            "{$method}() contains duplicate terms",
        );
    }

    // ──────────────────────────────────────────────
    //  Formula methods return non-empty strings
    // ──────────────────────────────────────────────

    /**
     * @return iterable<string, array{string}>
     */
    public static function formulaProvider(): iterable
    {
        yield 'basicFormula' => ['basicFormula'];
        yield 'advancedFormula' => ['advancedFormula'];
        yield 'imageToVideoFormula' => ['imageToVideoFormula'];
        yield 'soundFormula' => ['soundFormula'];
        yield 'r2vFormula' => ['r2vFormula'];
        yield 'multiShotFormula' => ['multiShotFormula'];
        yield 'voiceFormula' => ['voiceFormula'];
        yield 'soundEffectFormula' => ['soundEffectFormula'];
        yield 'bgmFormula' => ['bgmFormula'];
    }

    #[Test]
    #[DataProvider('formulaProvider')]
    public function formula_returns_non_empty_string(string $method): void
    {
        $formula = PromptDictionary::$method();

        $this->assertIsString($formula);
        $this->assertNotEmpty(trim($formula), "{$method}() must not be blank");
    }

    // ──────────────────────────────────────────────
    //  buildAestheticControl
    // ──────────────────────────────────────────────

    #[Test]
    public function build_aesthetic_control_joins_selections(): void
    {
        $result = PromptDictionary::buildAestheticControl([
            'daylight',
            'soft light',
            'close-up',
        ]);

        $this->assertSame('daylight, soft light, close-up', $result);
    }

    #[Test]
    public function build_aesthetic_control_strips_blanks(): void
    {
        $result = PromptDictionary::buildAestheticControl([
            'daylight',
            '',
            '  ',
            'rim light',
        ]);

        $this->assertSame('daylight, rim light', $result);
    }

    #[Test]
    public function build_aesthetic_control_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', PromptDictionary::buildAestheticControl([]));
    }

    #[Test]
    public function build_aesthetic_control_trims_whitespace(): void
    {
        $result = PromptDictionary::buildAestheticControl([
            '  daylight  ',
            ' soft light ',
        ]);

        $this->assertSame('daylight, soft light', $result);
    }

    // ──────────────────────────────────────────────
    //  allTerms
    // ──────────────────────────────────────────────

    #[Test]
    public function all_terms_is_non_empty(): void
    {
        $all = PromptDictionary::allTerms();

        $this->assertNotEmpty($all);
    }

    #[Test]
    public function all_terms_contains_no_duplicates(): void
    {
        $all = PromptDictionary::allTerms();

        $this->assertCount(
            count(array_unique($all)),
            $all,
            'allTerms() contains duplicate entries',
        );
    }

    #[Test]
    public function all_terms_contains_terms_from_every_category(): void
    {
        $all = PromptDictionary::allTerms();

        foreach (PromptDictionary::categories() as $method) {
            $categoryTerms = PromptDictionary::$method();

            foreach ($categoryTerms as $term) {
                $this->assertContains(
                    $term,
                    $all,
                    "allTerms() is missing '{$term}' from {$method}()",
                );
            }
        }
    }

    // ──────────────────────────────────────────────
    //  allFormulas
    // ──────────────────────────────────────────────

    #[Test]
    public function all_formulas_returns_expected_keys(): void
    {
        $formulas = PromptDictionary::allFormulas();

        $expectedKeys = [
            'basic',
            'advanced',
            'imageToVideo',
            'sound',
            'r2v',
            'multiShot',
            'voice',
            'soundEffect',
            'bgm',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $formulas, "Missing formula key: {$key}");
            $this->assertNotEmpty($formulas[$key]);
        }
    }

    // ──────────────────────────────────────────────
    //  categories helper
    // ──────────────────────────────────────────────

    #[Test]
    public function categories_maps_to_callable_methods(): void
    {
        foreach (PromptDictionary::categories() as $label => $method) {
            $this->assertTrue(
                method_exists(PromptDictionary::class, $method),
                "Category '{$label}' maps to non-existent method '{$method}'",
            );
        }
    }
}
