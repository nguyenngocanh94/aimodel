<?php

declare(strict_types=1);

/**
 * Skill discovery and caching configuration for the laravel-ai-sdk-skills compatible layer.
 *
 * Supports both Lite (name + description only) and Full (inline instructions) discovery modes.
 * Skills are defined as SKILL.md files in resources/skills/{slug}/ with YAML frontmatter.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Discovery mode
    |--------------------------------------------------------------------------
    |
    | Controls how skills are surfaced to agents by default:
    | - "lite": agents see only skill name + description (recommended for Telegram).
    | - "full": agents receive the full skill instructions inline.
    |
    */
    'discovery_mode' => env('SKILLS_DISCOVERY_MODE', 'lite'),

    /*
    |--------------------------------------------------------------------------
    | Discovery paths
    |--------------------------------------------------------------------------
    |
    | Directories scanned for SKILL.md files. Each path should contain
    | subdirectories named by skill slug (e.g. resources/skills/list-workflows/SKILL.md).
    |
    */
    'discovery_paths' => [
        resource_path('skills'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache settings
    |--------------------------------------------------------------------------
    |
    | When enabled, skill metadata is cached to avoid repeated filesystem reads.
    | Enable in production; leave disabled locally so edits take effect immediately.
    |
    */
    'cache_enabled' => env('SKILLS_CACHE_ENABLED', false),
    'cache_store'   => env('SKILLS_CACHE_STORE', 'file'),
    'cache_ttl'    => env('SKILLS_CACHE_TTL', 3600),
];
