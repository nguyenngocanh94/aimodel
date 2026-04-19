<?php

declare(strict_types=1);

/**
 * Fixture C — Aesthetic mood piece.
 *
 * Tests that the planner picks moodSequencer INSTEAD of storyWriter when the
 * brief explicitly rejects narrative. If the planner still chooses storyWriter
 * + humor, vibe drift in a different direction than fixture A.
 *
 * NOTE: `moodSequencer` node does not yet exist in the registry (645.4). This
 * fixture treats it as a planned node and is flagged in the design doc.
 */
return [
    'id' => 'milktea-aesthetic-mood',
    'brief' => 'Video cảm hứng 20s cho quán trà sữa mới mở "Mây Chiều". '
        . 'Không cần cốt truyện, không cần lời thoại, chỉ cần đẹp và có mood. '
        . 'Cảm giác slow-paced, satisfying, aesthetic. Kiểu video mở quán cho '
        . 'Instagram Reels / TikTok FYP thẩm mỹ. Không cần call-to-action.',
    'product' => 'Trà sữa Mây Chiều (small-batch creative milk tea)',
    'expectedVibeMode' => 'aesthetic_mood',
    'expectedNodes' => [
        'productAnalyzer',
        'moodSequencer', // planned node — see design doc limits section.
        'sceneSplitter',
        'promptRefiner',
        'imageGenerator',
        'wanR2V',
        'videoComposer',
    ],
    'forbiddenNodes' => [
        'storyWriter (brief explicitly says no storyline — selecting it is vibe drift)',
        'scriptWriter (brief says no dialogue)',
        'trendResearcher (trending hooks typically inject narrative/humor)',
    ],
    'expectedKnobValues' => [
        'moodSequencer.sensory_focus' => 'ritual',
        'moodSequencer.audio_priority' => 'asmr_sounds',
        'moodSequencer.text_density' => 'product_name_only',
        'moodSequencer.pacing' => 'slow_meditative',
        'moodSequencer.max_moments' => 4,
        'moodSequencer.target_duration_sec' => 20,
    ],
    'expectedCharacteristics' => [
        'productMentionsFirstThreeSeconds' => false, // not via dialogue
        'dialoguePresent' => false,
        'humorPresent' => false,
        'narrativeStructure' => 'sensory_flow',
        'hasTwistEnding' => false,
        'pacingCategory' => 'slow',
        'hasExplicitCallToAction' => false,
        'aestheticCoherenceMinScore' => 0.7, // 645.5 defines.
    ],
    'antiPatterns' => [
        'Character talking to camera (brief says no dialogue)',
        'Problem-agitation-solution structure (brief says no storyline)',
        'Fast cuts / rapid-fire editing (brief says slow-paced)',
        'Voiceover reciting menu items',
        'CTA overlay "Đặt ngay trên GrabFood" (brief excludes CTA)',
    ],
    'sourceNotes' => 'Vibe-drift test in the OPPOSITE direction from fixture A. '
        . 'If the planner defaults to storyWriter here just because it is the '
        . 'most-developed node, that is drift — the brief structurally forbids '
        . 'narrative. Real creative pattern: cafe-opening aesthetic reels '
        . '(@somiandthecity, @thecoffeehouse.vn content style).',
];
