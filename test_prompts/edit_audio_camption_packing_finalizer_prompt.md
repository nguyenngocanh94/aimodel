# Step 8 Prompt — Edit Audio Caption Packaging Finalizer

```text
You are the "Edit Audio Caption Packaging" node.

Task:
Take the provided `production_pack` and produce a final post-ready packaging plan:
- precise edit timeline
- caption overlays timing
- voiceover pacing
- music/sfx map
- export settings
- platform posting package

Do NOT change claims or intent.
Do NOT add new medical/comparative claims.
Keep all wording compliant with grounding constraints.

Output requirements:
- Return valid JSON only.
- Use exact schema below.

Return exactly:
{
  "final_packaging": {
    "timeline_edit_map": [
      {
        "timecode_start": "00:00.0",
        "timecode_end": "00:00.0",
        "shot_id": "string",
        "edit_instruction": "string",
        "transition": "hard_cut | jump_cut | speed_ramp_light | zoom_in_light | none"
      }
    ],
    "caption_overlay_timing": [
      {
        "timecode_start": "00:00.0",
        "timecode_end": "00:00.0",
        "text_vi": "string",
        "style": "minimal_clean | bold_pop | checklist",
        "position": "top | center | lower_third"
      }
    ],
    "voiceover_direction": {
      "pace_wpm_range": "string",
      "tone": "string",
      "pause_points": ["string"],
      "emphasis_words_vi": ["string"]
    },
    "audio_design": {
      "music_style": "string",
      "music_loudness_target_db": "string",
      "ducking_rule": "string",
      "sfx_plan": ["string"]
    },
    "thumbnail_plan": {
      "frame_source_shot_id": "string",
      "title_text_vi": "string",
      "visual_rule": "string"
    },
    "posting_package": {
      "final_caption_vi": "string",
      "pinned_comment_vi": "string",
      "hashtags_vi": ["string"],
      "cta_variant_a_vi": "string",
      "cta_variant_b_vi": "string"
    },
    "export_settings": {
      "resolution": "1080x1920",
      "fps": 30,
      "codec": "h264",
      "bitrate_mbps_range": "string",
      "audio": "aac 48khz"
    },
    "final_compliance_gate": {
      "passes": true,
      "checks": ["string"],
      "must_fix_if_any": ["string"]
    }
  }
}

Rules:
1) Keep total runtime aligned with production_pack target_duration_sec.
2) Captions must add clarity/subtext, not duplicate every VO word.
3) Ensure first 3 seconds are high-contrast and instantly readable.
4) Keep one consistent visual style, no over-styled transitions.
5) Soft CTA only (save/comment), no hard conversion pressure.
6) Normalize hashtags: lowercase, no diacritics, no spaces.

Input:
[PASTE_PRODUCTION_PACK_JSON_HERE]
```

