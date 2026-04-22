<?php

declare(strict_types=1);

/**
 * Fixture A — The Cocoon anti-pattern.
 *
 * 645.9 research diagnosed the Cocoon run as drifting into a TV-ad feel
 * because every node independently optimized for "show the product" without
 * an explicit vibe signal. This fixture encodes what the correct output
 * should look like so the drift becomes detectable.
 */
return [
    'id' => 'cocoon-soft-sell',
    'brief' => 'Tạo video TikTok 30s cho serum Cocoon bí đao N7. '
        . 'Tone vui vẻ, relatable GenZ Việt, kể một câu chuyện nhỏ đời thường '
        . '(kiểu "sáng ra soi gương thấy mụn sưng to"), đừng nói thẳng về sản phẩm, '
        . 'đừng đọc thành phần. Để sản phẩm xuất hiện tự nhiên ở cuối như một cú twist.',
    'product' => 'The Cocoon Original — Tinh chất bí đao N7 (Winter Melon Serum N7)',
    'expectedVibeMode' => 'funny_storytelling',
    'expectedNodes' => [
        'productAnalyzer',
        'storyWriter',
        'sceneSplitter',
        'promptRefiner',
        'imageGenerator',
        'wanR2V',
        'videoComposer',
    ],
    'forbiddenNodes' => [
        'trendResearcher (ingredient-breakdown trend pattern drags toward TV-ad)',
        'scriptWriter (explicit product-feature script drives hero-shot framing; use storyWriter instead)',
    ],
    'expectedKnobValues' => [
        'storyWriter.story_tension_curve' => 'fast_hit',
        'storyWriter.product_appearance_moment' => 'twist',
        'storyWriter.humor_density' => 'throughout',
        'storyWriter.ending_type_preference' => 'twist_reveal',
        'storyWriter.storyFormula' => 'problem_agitation_solution',
        'storyWriter.emotionalTone' => 'relatable_humor',
        'storyWriter.productIntegrationStyle' => 'subtle_background',
    ],
    'expectedCharacteristics' => [
        'productMentionsFirstThreeSeconds' => false,
        'humorPresent' => true,
        'narrativeStructure' => 'problem_agitation_solution',
        'hasTwistEnding' => true,
        'ingredientListReadAloud' => false,
        'heroProductShotBeforeSecond20' => false,
        'adLikenessMaxScore' => 0.35, // 645.5 defines the scoring scale.
    ],
    'antiPatterns' => [
        'Ingredient breakdown monologue (the Cocoon drift signal)',
        'Direct product hero shot in first 3 seconds',
        'Voiceover reciting "7% Niacinamide, 4% NAG, 0.8% BHA"',
        'Feature-comparison framing ("tốt hơn serum khác")',
        'Call-to-action ending ("Mua ngay trên Shopee")',
    ],
    'sourceNotes' => 'The canonical soft-sell funny-storytelling brief. Mirrors the '
        . 'real Cocoon pipeline run where the planner picked ingredient-breakdown '
        . 'formats because no node explicitly encoded "stay story-driven, product '
        . 'enters late". If a future planner run on this brief produces an '
        . 'ingredient-led or hero-shot-early structure, vibe drift has occurred. '
        . 'Real creative pattern: GenZ TikTok relatable-story-with-product-as-punchline '
        . '(e.g. skincare-girl POV content from @linhkarose, @trinhphamm).',
];
