<?php

declare(strict_types=1);

/**
 * Fixture D — Raw authentic testimonial (UGC feel).
 *
 * Tests that the planner can land a "friend-telling-friend" vibe without
 * drifting into either polished-TVC or narrative-comedy. This is the hardest
 * vibe to get right: the planner must resist BOTH storyWriter's humor-density
 * defaults AND the image pipeline's polish-everything defaults.
 *
 * NOTE: The exact "rawAuthenticCaster" / "ugcCaster" node type is planned but
 * not yet in the registry (645.4). Until then, the fixture relies on
 * storyWriter with humor_density=none + product_appearance_moment=middle to
 * approximate the raw-authentic shape, plus forbiddenNodes to block polish.
 */
return [
    'id' => 'chocopie-raw-authentic',
    'brief' => 'Video 25s "bạn-kể-bạn-nghe" về bánh ChocoPie. '
        . 'Cảm giác UGC thật — như bạn quay điện thoại dọc, nói chuyện với camera '
        . 'về việc ăn ChocoPie hồi cấp 2. Không hook mạnh, không twist, không hài cố tình. '
        . 'Talking head là chính, natural lighting, minimal editing, không voiceover, '
        . 'không text overlay quá sạch. Sản phẩm chỉ được giới thiệu chân thật ở giữa.',
    'product' => 'Orion ChocoPie',
    'expectedVibeMode' => 'raw_authentic',
    'expectedNodes' => [
        'productAnalyzer',
        'storyWriter', // used in raw mode per StoryWriterTemplate.plannerGuide()
        'sceneSplitter',
        'promptRefiner',
        'imageGenerator',
        'wanR2V',
        'videoComposer',
    ],
    'forbiddenNodes' => [
        'trendResearcher (trending hooks contradict "không hook mạnh")',
        'scriptWriter (polished script contradicts UGC authenticity)',
        'subtitleFormatter (clean-subtitle styling would make it feel produced; UGC uses raw auto-caption at most)',
    ],
    'expectedKnobValues' => [
        'storyWriter.story_tension_curve' => 'slow_build',
        'storyWriter.product_appearance_moment' => 'middle',
        'storyWriter.humor_density' => 'none',
        'storyWriter.ending_type_preference' => 'emotional_beat',
        'storyWriter.emotionalTone' => 'nostalgic',
        'storyWriter.productIntegrationStyle' => 'natural_use',
        'storyWriter.genZAuthenticity' => 'ultra',
        'imageGenerator.stylePreset' => 'raw_phone_camera', // planned knob per 645.4
        'videoComposer.polishLevel' => 'minimal',           // planned knob per 645.4
    ],
    'expectedCharacteristics' => [
        'productMentionsFirstThreeSeconds' => false,
        'humorPresent' => false,
        'hasTalkingHead' => true,
        'narrativeStructure' => 'personal_anecdote',
        'hasTwistEnding' => false,
        'pacingCategory' => 'natural',
        'productionPolishMaxScore' => 0.4, // 645.5 defines polish scale.
        'feelsUgcMinScore' => 0.7,
    ],
    'antiPatterns' => [
        'Cinematic b-roll / slow-mo product drop (breaks UGC feel)',
        'Voiceover narration over edited shots (brief rules this out)',
        'Big hook in first second (brief says không hook mạnh)',
        'Multiple actors / staged dialogue',
        'Clean kinetic typography subtitles',
        'Punchline humor (contradicts nostalgic UGC register)',
    ],
    'sourceNotes' => 'The hardest vibe. Raw-authentic content is defined as much '
        . 'by what it lacks (polish, hooks, production value) as by what it '
        . 'contains. Planner success here means actively suppressing defaults '
        . 'of nodes designed for polished content. Real creative pattern: '
        . 'Vietnamese TikTok "storytime" posts — single creator, phone camera, '
        . 'one take (e.g. student nostalgia / "hồi cấp 2" content).',
];
