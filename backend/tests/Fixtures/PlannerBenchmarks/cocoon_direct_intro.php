<?php

declare(strict_types=1);

/**
 * Fixture B — Direct product intro (product-centered IS correct).
 *
 * Contrasts with fixture A: same product, but the brief explicitly asks for
 * an educational ingredient breakdown. A planner that defaults to
 * funny_storytelling here is equally wrong — the vibe-mode must be
 * explicitly selected from the brief, not from a global default.
 */
return [
    'id' => 'cocoon-direct-intro',
    'brief' => 'Video 15s giới thiệu serum Cocoon bí đao N7. '
        . 'Nhấn mạnh 3 thành phần chính: Niacinamide 7%, BHA 0.8%, chiết xuất bí đao. '
        . 'Clean aesthetic, tone giáo dục, không cần hài hước, không cần kể chuyện. '
        . 'Sản phẩm xuất hiện sớm, có call-to-action ở cuối.',
    'product' => 'The Cocoon Original — Tinh chất bí đao N7 (Winter Melon Serum N7)',
    'expectedVibeMode' => 'clean_education',
    'expectedNodes' => [
        'productAnalyzer',
        'scriptWriter',
        'sceneSplitter',
        'promptRefiner',
        'imageGenerator',
        'videoComposer',
    ],
    'forbiddenNodes' => [
        'storyWriter (narrative framing drifts away from educational-breakdown intent)',
        'trendResearcher (trend pattern would inject humor/story which brief excludes)',
    ],
    'expectedKnobValues' => [
        // Note: scriptWriter does not yet expose vibe-aware knobs with explicit
        // `clean_education` mappings; values below are the DESIRED resolution
        // after 645.4 aligns scriptWriter to the vibe contract.
        'scriptWriter.tone' => 'educational',
        'scriptWriter.productIntegrationStyle' => 'hero_moment',
        'scriptWriter.targetDurationSeconds' => 15,
        'scriptWriter.includeCallToAction' => true,
        'sceneSplitter.style' => 'ingredient_breakdown',
    ],
    'expectedCharacteristics' => [
        'productMentionsFirstThreeSeconds' => true,
        'humorPresent' => false,
        'narrativeStructure' => 'feature_breakdown',
        'hasTwistEnding' => false,
        'ingredientListReadAloud' => true,
        'heroProductShotBeforeSecond20' => true,
        'hasExplicitCallToAction' => true,
        'adLikenessMaxScore' => 1.0, // acceptable to feel ad-like here.
        'adLikenessMinScore' => 0.4,  // but still must be clean, not cheesy.
    ],
    'antiPatterns' => [
        'Fictional storyline with invented character (brief asks for no story)',
        'Humor injected despite brief saying "không cần hài hước"',
        'Subtle-background product integration (brief requires early hero shot)',
        'Ingredient claims exaggerated beyond source (must_not_exaggerate constraint)',
    ],
    'sourceNotes' => 'The control fixture. Proves the planner can cleanly land a '
        . 'product-centered brief when the brief actually calls for one. Without '
        . 'this contrast, fixture A could accidentally test "never show product '
        . 'early" as a universal rule. Real creative pattern: skincare-brand '
        . 'educational-first content (e.g. Paula\'s Choice, La Roche-Posay Vietnam).',
];
