<?php

declare(strict_types=1);

namespace App\Domain\Wan;

/**
 * Controlled vocabulary extracted from the official Wan 2.6/2.7 prompting guide.
 *
 * Every term in this class is known to produce a reliable visual response from
 * the Wan video-generation model.  The PromptRefiner node uses these arrays to
 * validate and compose aesthetic-control fragments.
 *
 * @see wan2.6_guide/modelstudio-console.md
 */
class PromptDictionary
{
    // ──────────────────────────────────────────────
    //  Prompt formula templates
    // ──────────────────────────────────────────────

    public static function basicFormula(): string
    {
        return 'Entity + Scene + Motion';
    }

    public static function advancedFormula(): string
    {
        return 'Entity (description) + Scene (description) + Motion (description) + Aesthetic control + Stylization';
    }

    public static function imageToVideoFormula(): string
    {
        return 'Motion + Camera movement';
    }

    public static function soundFormula(): string
    {
        return 'Entity + Scene + Motion + Sound description (voice/sound effect/background music)';
    }

    public static function r2vFormula(): string
    {
        return 'Character + Action + Lines + Scene';
    }

    public static function multiShotFormula(): string
    {
        return 'Overall description + Shot number + Timestamp + Shot content';
    }

    // ──────────────────────────────────────────────
    //  Sound sub-formula templates
    // ──────────────────────────────────────────────

    public static function voiceFormula(): string
    {
        return "Character's lines + Emotion + Tone + Speed + Timbre + Accent";
    }

    public static function soundEffectFormula(): string
    {
        return 'Source material + Action + Ambient sound';
    }

    public static function bgmFormula(): string
    {
        return 'Background music/score + Style';
    }

    // ──────────────────────────────────────────────
    //  Light Source
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function lightSources(): array
    {
        return [
            'daylight',
            'firelight',
            'overcast light',
            'clear sky light',
            'moonlight',
            'practical light',
            'fluorescent light',
            'neon light',
            'screen light',
        ];
    }

    // ──────────────────────────────────────────────
    //  Lighting Environment
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function lightingEnvironments(): array
    {
        return [
            'soft light',
            'hard light',
            'side light',
            'high contrast',
            'low contrast',
            'rim light',
            'backlight',
            'top light',
            'mixed light',
        ];
    }

    // ──────────────────────────────────────────────
    //  Lighting Time
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function lightingTimes(): array
    {
        return [
            'daytime',
            'night',
            'dawn',
            'dusk',
            'sunset',
            'sunrise',
        ];
    }

    // ──────────────────────────────────────────────
    //  Shot Size
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function shotSizes(): array
    {
        return [
            'close-up',
            'medium close-up',
            'close shot',
            'medium shot',
            'medium full shot',
            'full shot',
            'wide-angle',
            'extreme full shot',
        ];
    }

    // ──────────────────────────────────────────────
    //  Shot Composition
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function shotCompositions(): array
    {
        return [
            'center composition',
            'left-weighted composition',
            'left-heavy composition',
            'right-weighted composition',
            'symmetrical composition',
            'balanced composition',
            'short-side composition',
        ];
    }

    // ──────────────────────────────────────────────
    //  Lens
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function lenses(): array
    {
        return [
            'long-focus',
            'long-focus lens',
            'wide-angle',
            'ultra-wide-angle fisheye',
            'medium-focus',
            'medium focal length',
            'tilt-shift lens',
        ];
    }

    // ──────────────────────────────────────────────
    //  Camera Angle
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function cameraAngles(): array
    {
        return [
            'eye-level',
            'eye-level shot',
            'over-the-shoulder shot',
            'high-angle shot',
            'low-angle shot',
            'aerial shot',
            'top-down shot',
            'top-down angle shot',
            'tilted angle',
        ];
    }

    // ──────────────────────────────────────────────
    //  Camera Movement
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function cameraMovements(): array
    {
        return [
            'clean single shot',
            'camera pushes in',
            'camera pushes in slowly',
            'camera moves left',
            'camera moves right',
            'camera pans horizontally',
            'tracking',
            'pan',
            'fixed camera',
            'camera slowly pushes forward',
            'establishing shot',
            'two-shot',
            'group shot',
        ];
    }

    // ──────────────────────────────────────────────
    //  Stylization
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function stylizations(): array
    {
        return [
            'cyberpunk',
            'line art illustration',
            'wasteland style',
            'felt style',
            'stop-motion animation',
            '8-bit pixel style',
            'VHS glitch aesthetic',
            'steampunk style',
            'surreal style',
            'ASMR',
            'CRT scanline effect',
        ];
    }

    // ──────────────────────────────────────────────
    //  Color / Tone
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function tones(): array
    {
        return [
            'warm tones',
            'cool tones',
            'warm colors',
            'mixed tones',
            'low saturation',
            'high saturation',
        ];
    }

    // ──────────────────────────────────────────────
    //  Sound types
    // ──────────────────────────────────────────────

    /** @return string[] */
    public static function soundTypes(): array
    {
        return [
            'single speaker',
            'group conversation',
            'timbre',
            'singing',
            'footsteps',
            'knocking',
            'object falling',
            'impact sound',
            'fire burning',
            'game sound effects',
            'electronic sound effects',
            'ASMR',
            'animal sounds',
            'keyboard sounds',
        ];
    }

    /** @return string[] */
    public static function musicTypes(): array
    {
        return [
            'emotional music',
            'beat-synced music',
            'light music',
            'natural environment',
            'urban environment',
            'specific space',
        ];
    }

    // ──────────────────────────────────────────────
    //  Builders / Helpers
    // ──────────────────────────────────────────────

    /**
     * Build an aesthetic-control prompt fragment from category selections.
     *
     * Each value in $selections should be a term drawn from one of the
     * category methods (lightSources, lightingEnvironments, etc.).
     *
     * @param  string[]  $selections
     */
    public static function buildAestheticControl(array $selections): string
    {
        $filtered = array_filter(
            array_map('trim', $selections),
            fn (string $s): bool => $s !== '',
        );

        return implode(', ', $filtered);
    }

    /**
     * All known vocabulary terms across every category (flat, unique).
     *
     * @return string[]
     */
    public static function allTerms(): array
    {
        $merged = array_merge(
            self::lightSources(),
            self::lightingEnvironments(),
            self::lightingTimes(),
            self::shotSizes(),
            self::shotCompositions(),
            self::lenses(),
            self::cameraAngles(),
            self::cameraMovements(),
            self::stylizations(),
            self::tones(),
            self::soundTypes(),
            self::musicTypes(),
        );

        return array_values(array_unique($merged));
    }

    /**
     * All formula templates keyed by name.
     *
     * @return array<string, string>
     */
    public static function allFormulas(): array
    {
        return [
            'basic' => self::basicFormula(),
            'advanced' => self::advancedFormula(),
            'imageToVideo' => self::imageToVideoFormula(),
            'sound' => self::soundFormula(),
            'r2v' => self::r2vFormula(),
            'multiShot' => self::multiShotFormula(),
            'voice' => self::voiceFormula(),
            'soundEffect' => self::soundEffectFormula(),
            'bgm' => self::bgmFormula(),
        ];
    }

    /**
     * Map of category name => method name, useful for iteration.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'lightSources' => 'lightSources',
            'lightingEnvironments' => 'lightingEnvironments',
            'lightingTimes' => 'lightingTimes',
            'shotSizes' => 'shotSizes',
            'shotCompositions' => 'shotCompositions',
            'lenses' => 'lenses',
            'cameraAngles' => 'cameraAngles',
            'cameraMovements' => 'cameraMovements',
            'stylizations' => 'stylizations',
            'tones' => 'tones',
            'soundTypes' => 'soundTypes',
            'musicTypes' => 'musicTypes',
        ];
    }
}
