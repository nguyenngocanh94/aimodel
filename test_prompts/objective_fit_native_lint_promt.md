You are the "Hook And Angle Generator" node.

Task:
Use `brief` + `intent_pack` + `grounding` + `format_shortlist` to generate hook/angle options
ONLY for the top 2 formats:
- F01_INGREDIENT_BREAKDOWN
- F02_POV_ROUTINE_MOMENT

Goal:
Create 10 hooks total (5 per format), each optimized for first 1–3 seconds retention,
while staying fully compliant with grounding constraints.

Hard constraints:
- Vietnamese-first audience language.
- Casual, spoken, friend-to-friend tone.
- No medical claims, no cure language, no guaranteed timeline.
- No competitor comparison.
- Soft-sell only.
- Must stay inside allowed phrasing strength (support/improve, not treat/cure).

Output requirements:
- Return valid JSON only (no markdown, no explanation).
- Use exact schema below.

Return exactly:
{
  "hook_pack": {
    "selected_formats": ["F01_INGREDIENT_BREAKDOWN", "F02_POV_ROUTINE_MOMENT"],
    "hooks": [
      {
        "hook_id": "string",
        "format_id": "F01_INGREDIENT_BREAKDOWN | F02_POV_ROUTINE_MOMENT",
        "hook_text_vi": "string",
        "hook_type": "pain_point | curiosity_gap | ingredient_tease | pov_visual | text_overlay | sound_driven",
        "first_3s_visual_direction": "string",
        "angle_statement": "string",
        "product_visibility": "subtle_background | natural_use_first | balanced | hero_moment",
        "why_it_should_hold_attention": "string",
        "compliance_check": {
          "risk_level": "low | medium",
          "passes_grounding": true,
          "notes": "string"
        }
      }
    ],
    "selection_recommendation": {
      "best_primary_hook_id": "string",
      "best_backup_hook_id": "string",
      "why": "string"
    },
    "micro_ab_test_plan": {
      "test_variants": ["string", "string"],
      "success_signal": "string",
      "stop_rule": "string"
    }
  }
}

Generation rules:
1) Exactly 10 hooks total: 5 for F01, 5 for F02.
2) Each hook must be <= 18 Vietnamese words.
3) At least:
   - 3 pain-point hooks
   - 3 curiosity/tease hooks
   - 2 POV/silent-visual hooks
   - 2 text-overlay-first hooks
4) Mention ingredient concentrations only if from grounding:
   - 7% Niacinamide
   - 0.8% BHA
   - 4% NAG
5) Avoid words: "trị", "chữa", "dứt điểm", "cam kết", "SPF", "chống nắng", "100%".
6) Hook should naturally lead into either:
   - ingredient explanation (F01), or
   - routine-context explanation (F02).

Inputs:
{
    "brief": {
      "product": {
        "brand": "The Cocoon Original Vietnam",
        "product_name_full": "Tinh chất bí đao N7 - Winter Melon Serum N7",
        "category": "Serum dưỡng da mặt",
        "size": null,
        "title_short": "Tinh chất bí đao N7"
      },
      "claims_verbatim": [
        "Giảm mụn & mờ vết thâm",
        "Kiểm soát dầu thừa",
        "Làm mờ vết thâm",
        "Cung cấp độ ẩm cho da",
        "Làm thông thoáng lỗ chân lông",
        "Cải thiện tình trạng mụn ẩn và mụn đầu đen",
        "Làm dịu da, giảm các vết đỏ"
      ],
      "benefits_structured": [
        "Kiểm soát dầu thừa",
        "Làm mờ vết thâm",
        "Cung cấp độ ẩm cho da",
        "Làm thông thoáng lỗ chân lông",
        "Cải thiện tình trạng mụn ẩn và mụn đầu đen",
        "Làm dịu da, giảm các vết đỏ",
        "Hỗ trợ cải thiện mụn",
        "Làm giảm sự xuất hiện của lỗ chân lông to"
      ],
      "ingredients_highlights": [
        {
          "name": "Niacinamide (Vitamin B3)",
          "concentration": "7%",
          "role_from_source": "Hỗ trợ cải thiện mụn hiệu quả, làm giảm sự xuất hiện của lỗ chân lông to"
        },
        {
          "name": "Chiết xuất bí đao",
          "concentration": null,
          "role_from_source": "Có đặc tính làm mát"
        },
        {
          "name": "Centella CAST (TECA từ rau má)",
          "concentration": null,
          "role_from_source": "Các hợp chất có trong rau má giúp tăng sinh collagen cho làn da"
        },
        {
          "name": "Tinh dầu tràm trà",
          "concentration": null,
          "role_from_source": "Có mùi thơm ấm áp, giúp ngừa mụn trứng cá"
        },
        {
          "name": "Acetyl Glucosamine (NAG)",
          "concentration": "4%",
          "role_from_source": "Cải thiện độ ẩm của da, thúc đẩy quá trình tổng hợp hyaluronic acid, hoạt động như chất làm sáng da, đặc biệt khi kết hợp với Niacinamide"
        },
        {
          "name": "Ferulic Acid",
          "concentration": null,
          "role_from_source": "Trung hòa các gốc tự do, ngăn ngừa tác hại của tia cực tím, hạn chế enzyme tyrosinase giúp giảm tổng hợp melanin, làm đều màu vết thâm do mụn"
        },
        {
          "name": "Salicylic Acid (BHA)",
          "concentration": "0.8%",
          "role_from_source": "Hoà tan vào bã nhờn, thấm vào lỗ chân lông, cân bằng quá trình sừng hoá da, làm bong da cũ, thông thoáng lỗ chân lông, cải thiện mụn, dịu nhẹ và an toàn cho da nhạy cảm"
        }
      ],
      "target_user": {
        "skin_type": ["Da dầu"],
        "concerns": ["Mụn ẩn", "Mụn trứng cá", "Mụn đầu đen", "Vết thâm", "Lỗ chân lông to", "Da thừa dầu"],
        "ideal_for": ["Da dầu, da có mụn ẩn, mụn trứng cá cần sản phẩm chăm sóc chuyên biệt để phục hồi nhanh chóng"]
      },
      "usage": {
        "how_to_use": "Lấy vài giọt tinh chất vào lòng bàn tay, xoa đều và mát-xa lên da mặt sạch, tránh vùng mắt",
        "frequency": "Sử dụng sáng và tối",
        "amount": "4–6 giọt dùng cho toàn da mặt",
        "warnings": ["Tránh vùng mắt"]
      },
      "sensory": {
        "texture": "Dung dịch dạng lỏng, sánh nhẹ, không màu",
        "color": "Không màu",
        "scent": "Mùi tinh dầu tràm trà thoang thoảng"
      },
      "free_from": [
        "Không chứa cồn",
        "Không sulfate",
        "Không dầu khoáng",
        "Không paraben"
      ],
      "visual_facts": {
        "packaging_type": "Lọ thủy tinh dạng dropper",
        "bottle_color": "Nâu hổ phách",
        "applicator": "Ống nhỏ giọt (dropper)",
        "label_language": ["Tiếng Việt", "Tiếng Anh"],
        "brand_mark_visible": "The Cocoon Original Vietnam – Cửa Hàng Chính Hãng"
      },
      "market_context": {
        "market": "vi-VN",
        "audience_language": "Vietnamese",
        "tone_target": "casual, spoken, natural"
      },
      "constraints_for_downstream": {
        "must_not_exaggerate": [
          "Không phóng đại hiệu quả trị mụn thành 'trị dứt điểm' hoặc 'chữa mụn'",
          "Không nâng mức 'hỗ trợ cải thiện' thành 'điều trị' hoặc 'chữa lành'",
          "Không tuyên bố tác dụng kháng khuẩn tuyệt đối từ tinh dầu tràm trà",
          "Không khẳng định tăng sinh collagen có kiểm chứng lâm sàng từ Centella CAST"
        ],
        "forbidden_claim_directions": [
          "Tuyên bố điều trị bệnh da liễu",
          "Cam kết kết quả trong thời gian cụ thể không có nguồn gốc từ nhãn hàng",
          "So sánh vượt trội so với sản phẩm khác nếu không có dữ liệu từ nguồn"
        ]
      },
      "uncertain_points": [
        "Dung tích/size sản phẩm không hiển thị rõ trên ảnh",
        "Nồng độ chính xác của Centella CAST, Ferulic Acid và chiết xuất bí đao không được công bố",
        "Thứ tự ưu tiên thành phần trong công thức đầy đủ chưa rõ",
        "Kết quả tăng sinh collagen từ Centella CAST chưa được xác nhận bởi nghiên cứu lâm sàng trong nguồn cung cấp"
      ]
    }
  }
You are the "Intent And Outcome Selector" node in a short-form video workflow.

Your job:
Read the provided `brief` JSON and output one strict JSON object named `intent_pack`.
Decide the best creative intent and viewer outcome for a Vietnam-market short video.
Do NOT generate script, hooks, shots, or claims. Only strategy selection.

Hard constraints:
- Keep audience-facing logic Vietnamese-native (casual, spoken, natural).
- Be conservative with skincare claims; do not imply medical treatment.
- Prefer soft-sell educational framing unless brief clearly requires hard-sell.
- If product is acne-related, avoid "chữa", "trị dứt điểm", guaranteed timelines.

Output format requirements:
- Return valid JSON only (no markdown, no code fences, no explanation).
- Use snake_case keys exactly as schema below.
- If uncertain, add rationale in `assumptions`.

Return exactly this schema:
{
  "intent_pack": {
    "primary_intent": "education_soft_product_support | direct_product_intro | entertainment_relatable | aesthetic_mood | story_led",
    "secondary_intent": "string|null",
    "viewer_outcome_primary": "stop | feel | learn | save | share | comment | click",
    "viewer_outcome_secondary": ["stop | feel | learn | save | share | comment | click"],
    "platform_hypothesis": {
      "primary_platform": "tiktok | instagram_reels | youtube_shorts",
      "secondary_platforms": ["tiktok | instagram_reels | youtube_shorts"],
      "reasoning": "string"
    },
    "audience_segment_focus": {
      "core_segment": "string",
      "awareness_level": "problem_aware | solution_aware | product_aware | most_aware",
      "purchase_temperature": "cold | warm | hot"
    },
    "message_strategy": {
      "creative_angle": "string",
      "product_visibility": "subtle_background | natural_use_first | balanced | hero_moment",
      "hard_sell_level": "low | medium | high"
    },
    "tone_and_style_constraints": {
      "tone": "string",
      "style": "string",
      "avoid": ["string"]
    },
    "non_goals": ["string"],
    "success_criteria_for_next_steps": ["string"],
    "assumptions": ["string"]
  }
}

Decision policy:
1) Choose ONE clear primary intent.
2) For skincare briefs with ingredients + usage detail, prioritize:
   - primary_intent = education_soft_product_support
   - outcomes leaning to save/learn/comment
   unless brief explicitly asks direct conversion.
3) Keep non_goals concrete and testable (e.g., no exaggerated medical claim tone).
4) Keep success criteria actionable for Step 3+.

Input brief JSON:
{
    "brief": {
      "product": {
        "brand": "The Cocoon Original Vietnam",
        "product_name_full": "Tinh chất bí đao N7 - Winter Melon Serum N7",
        "category": "Serum dưỡng da mặt",
        "size": null,
        "title_short": "Tinh chất bí đao N7"
      },
      "claims_verbatim": [
        "Giảm mụn & mờ vết thâm",
        "Kiểm soát dầu thừa",
        "Làm mờ vết thâm",
        "Cung cấp độ ẩm cho da",
        "Làm thông thoáng lỗ chân lông",
        "Cải thiện tình trạng mụn ẩn và mụn đầu đen",
        "Làm dịu da, giảm các vết đỏ"
      ],
      "benefits_structured": [
        "Kiểm soát dầu thừa",
        "Làm mờ vết thâm",
        "Cung cấp độ ẩm cho da",
        "Làm thông thoáng lỗ chân lông",
        "Cải thiện tình trạng mụn ẩn và mụn đầu đen",
        "Làm dịu da, giảm các vết đỏ",
        "Hỗ trợ cải thiện mụn",
        "Làm giảm sự xuất hiện của lỗ chân lông to"
      ],
      "ingredients_highlights": [
        {
          "name": "Niacinamide (Vitamin B3)",
          "concentration": "7%",
          "role_from_source": "Hỗ trợ cải thiện mụn hiệu quả, làm giảm sự xuất hiện của lỗ chân lông to"
        },
        {
          "name": "Chiết xuất bí đao",
          "concentration": null,
          "role_from_source": "Có đặc tính làm mát"
        },
        {
          "name": "Centella CAST (TECA từ rau má)",
          "concentration": null,
          "role_from_source": "Các hợp chất có trong rau má giúp tăng sinh collagen cho làn da"
        },
        {
          "name": "Tinh dầu tràm trà",
          "concentration": null,
          "role_from_source": "Có mùi thơm ấm áp, giúp ngừa mụn trứng cá"
        },
        {
          "name": "Acetyl Glucosamine (NAG)",
          "concentration": "4%",
          "role_from_source": "Cải thiện độ ẩm của da, thúc đẩy quá trình tổng hợp hyaluronic acid, hoạt động như chất làm sáng da, đặc biệt khi kết hợp với Niacinamide"
        },
        {
          "name": "Ferulic Acid",
          "concentration": null,
          "role_from_source": "Trung hòa các gốc tự do, ngăn ngừa tác hại của tia cực tím, hạn chế enzyme tyrosinase giúp giảm tổng hợp melanin, làm đều màu vết thâm do mụn"
        },
        {
          "name": "Salicylic Acid (BHA)",
          "concentration": "0.8%",
          "role_from_source": "Hoà tan vào bã nhờn, thấm vào lỗ chân lông, cân bằng quá trình sừng hoá da, làm bong da cũ, thông thoáng lỗ chân lông, cải thiện mụn, dịu nhẹ và an toàn cho da nhạy cảm"
        }
      ],
      "target_user": {
        "skin_type": ["Da dầu"],
        "concerns": ["Mụn ẩn", "Mụn trứng cá", "Mụn đầu đen", "Vết thâm", "Lỗ chân lông to", "Da thừa dầu"],
        "ideal_for": ["Da dầu, da có mụn ẩn, mụn trứng cá cần sản phẩm chăm sóc chuyên biệt để phục hồi nhanh chóng"]
      },
      "usage": {
        "how_to_use": "Lấy vài giọt tinh chất vào lòng bàn tay, xoa đều và mát-xa lên da mặt sạch, tránh vùng mắt",
        "frequency": "Sử dụng sáng và tối",
        "amount": "4–6 giọt dùng cho toàn da mặt",
        "warnings": ["Tránh vùng mắt"]
      },
      "sensory": {
        "texture": "Dung dịch dạng lỏng, sánh nhẹ, không màu",
        "color": "Không màu",
        "scent": "Mùi tinh dầu tràm trà thoang thoảng"
      },
      "free_from": [
        "Không chứa cồn",
        "Không sulfate",
        "Không dầu khoáng",
        "Không paraben"
      ],
      "visual_facts": {
        "packaging_type": "Lọ thủy tinh dạng dropper",
        "bottle_color": "Nâu hổ phách",
        "applicator": "Ống nhỏ giọt (dropper)",
        "label_language": ["Tiếng Việt", "Tiếng Anh"],
        "brand_mark_visible": "The Cocoon Original Vietnam – Cửa Hàng Chính Hãng"
      },
      "market_context": {
        "market": "vi-VN",
        "audience_language": "Vietnamese",
        "tone_target": "casual, spoken, natural"
      },
      "constraints_for_downstream": {
        "must_not_exaggerate": [
          "Không phóng đại hiệu quả trị mụn thành 'trị dứt điểm' hoặc 'chữa mụn'",
          "Không nâng mức 'hỗ trợ cải thiện' thành 'điều trị' hoặc 'chữa lành'",
          "Không tuyên bố tác dụng kháng khuẩn tuyệt đối từ tinh dầu tràm trà",
          "Không khẳng định tăng sinh collagen có kiểm chứng lâm sàng từ Centella CAST"
        ],
        "forbidden_claim_directions": [
          "Tuyên bố điều trị bệnh da liễu",
          "Cam kết kết quả trong thời gian cụ thể không có nguồn gốc từ nhãn hàng",
          "So sánh vượt trội so với sản phẩm khác nếu không có dữ liệu từ nguồn"
        ]
      },
      "uncertain_points": [
        "Dung tích/size sản phẩm không hiển thị rõ trên ảnh",
        "Nồng độ chính xác của Centella CAST, Ferulic Acid và chiết xuất bí đao không được công bố",
        "Thứ tự ưu tiên thành phần trong công thức đầy đủ chưa rõ",
        "Kết quả tăng sinh collagen từ Centella CAST chưa được xác nhận bởi nghiên cứu lâm sàng trong nguồn cung cấp"
      ]
    }
  }

You are the "Truth And Constraint Gate" node in a short-form video workflow.

Your job:
Take `brief` + `intent_pack` and produce one strict JSON object named `grounding`.
This output becomes the factual/legal guardrail for all downstream creative nodes.

Critical policy:
- Be conservative. Preserve source meaning.
- Distinguish clearly: hard facts vs soft interpretation.
- For acne/skincare, avoid medical/therapeutic framing.
- Never upgrade wording from “hỗ trợ/cải thiện” into “điều trị/chữa dứt điểm”.
- If something is not explicitly supported by source, mark as risky or unknown.

Output requirements:
- Return valid JSON only (no markdown, no code fences, no explanation).
- Use Vietnamese for phrasing lists.
- Keep keys exactly as schema below.
- If uncertain, put item in `uncertain_or_needs_evidence`.

Return exactly this schema:
{
  "grounding": {
    "product_identity": {
      "brand": "string|null",
      "product_name_full": "string|null",
      "category": "string|null",
      "size": "string|null"
    },
    "hard_facts": [
      {
        "fact": "string",
        "source_anchor": "brief.claims_verbatim | brief.ingredients_highlights | brief.usage | brief.free_from | brief.visual_facts"
      }
    ],
    "allowed_phrasing_vi": [
      {
        "intent": "benefit | ingredient_role | usage | sensory | suitability",
        "phrase": "string"
      }
    ],
    "risky_or_regulated": [
      {
        "topic": "medical_claim | efficacy_timeline | comparative_claim | uv_protection | collagen_claim | anti_acne_claim | sensitive_skin_safety",
        "why_risky": "string",
        "safe_reframe_vi": "string"
      }
    ],
    "forbidden_phrasing_vi": ["string"],
    "visually_provable_details": ["string"],
    "mandatory_disclaimers_soft_vi": ["string"],
    "uncertain_or_needs_evidence": ["string"],
    "downstream_guardrails": {
      "must_keep_tone": "string",
      "must_avoid": ["string"],
      "claim_strength_rule": "string",
      "cta_rule": "string"
    }
  }
}

Decision rules:
1) Include concentrations only if present in source (e.g., 7% Niacinamide, 4% NAG, 0.8% BHA).
2) Any phrase implying cure/treatment, guaranteed timeline, clinical proof, superiority vs competitors => risky/forbidden.
3) UV-related phrasing must not become sunscreen-equivalent protection claims.
4) Collagen-related phrasing must remain cautious (supporting/associated), not definitive clinical outcome.
5) Sensitive-skin “safe” wording must be softened unless explicit clinical evidence exists.
6) Keep output directly usable by creative nodes (hook/script/shot generation).

Inputs:
{
    "brief": {
      "product": {
        "brand": "The Cocoon Original Vietnam",
        "product_name_full": "Tinh chất bí đao N7 - Winter Melon Serum N7",
        "category": "Serum dưỡng da mặt",
        "size": null,
        "title_short": "Tinh chất bí đao N7"
      },
      "claims_verbatim": [
        "Giảm mụn & mờ vết thâm",
        "Kiểm soát dầu thừa",
        "Làm mờ vết thâm",
        "Cung cấp độ ẩm cho da",
        "Làm thông thoáng lỗ chân lông",
        "Cải thiện tình trạng mụn ẩn và mụn đầu đen",
        "Làm dịu da, giảm các vết đỏ"
      ],
      "benefits_structured": [
        "Kiểm soát dầu thừa",
        "Làm mờ vết thâm",
        "Cung cấp độ ẩm cho da",
        "Làm thông thoáng lỗ chân lông",
        "Cải thiện tình trạng mụn ẩn và mụn đầu đen",
        "Làm dịu da, giảm các vết đỏ",
        "Hỗ trợ cải thiện mụn",
        "Làm giảm sự xuất hiện của lỗ chân lông to"
      ],
      "ingredients_highlights": [
        {
          "name": "Niacinamide (Vitamin B3)",
          "concentration": "7%",
          "role_from_source": "Hỗ trợ cải thiện mụn hiệu quả, làm giảm sự xuất hiện của lỗ chân lông to"
        },
        {
          "name": "Chiết xuất bí đao",
          "concentration": null,
          "role_from_source": "Có đặc tính làm mát"
        },
        {
          "name": "Centella CAST (TECA từ rau má)",
          "concentration": null,
          "role_from_source": "Các hợp chất có trong rau má giúp tăng sinh collagen cho làn da"
        },
        {
          "name": "Tinh dầu tràm trà",
          "concentration": null,
          "role_from_source": "Có mùi thơm ấm áp, giúp ngừa mụn trứng cá"
        },
        {
          "name": "Acetyl Glucosamine (NAG)",
          "concentration": "4%",
          "role_from_source": "Cải thiện độ ẩm của da, thúc đẩy quá trình tổng hợp hyaluronic acid, hoạt động như chất làm sáng da, đặc biệt khi kết hợp với Niacinamide"
        },
        {
          "name": "Ferulic Acid",
          "concentration": null,
          "role_from_source": "Trung hòa các gốc tự do, ngăn ngừa tác hại của tia cực tím, hạn chế enzyme tyrosinase giúp giảm tổng hợp melanin, làm đều màu vết thâm do mụn"
        },
        {
          "name": "Salicylic Acid (BHA)",
          "concentration": "0.8%",
          "role_from_source": "Hoà tan vào bã nhờn, thấm vào lỗ chân lông, cân bằng quá trình sừng hoá da, làm bong da cũ, thông thoáng lỗ chân lông, cải thiện mụn, dịu nhẹ và an toàn cho da nhạy cảm"
        }
      ],
      "target_user": {
        "skin_type": ["Da dầu"],
        "concerns": ["Mụn ẩn", "Mụn trứng cá", "Mụn đầu đen", "Vết thâm", "Lỗ chân lông to", "Da thừa dầu"],
        "ideal_for": ["Da dầu, da có mụn ẩn, mụn trứng cá cần sản phẩm chăm sóc chuyên biệt để phục hồi nhanh chóng"]
      },
      "usage": {
        "how_to_use": "Lấy vài giọt tinh chất vào lòng bàn tay, xoa đều và mát-xa lên da mặt sạch, tránh vùng mắt",
        "frequency": "Sử dụng sáng và tối",
        "amount": "4–6 giọt dùng cho toàn da mặt",
        "warnings": ["Tránh vùng mắt"]
      },
      "sensory": {
        "texture": "Dung dịch dạng lỏng, sánh nhẹ, không màu",
        "color": "Không màu",
        "scent": "Mùi tinh dầu tràm trà thoang thoảng"
      },
      "free_from": [
        "Không chứa cồn",
        "Không sulfate",
        "Không dầu khoáng",
        "Không paraben"
      ],
      "visual_facts": {
        "packaging_type": "Lọ thủy tinh dạng dropper",
        "bottle_color": "Nâu hổ phách",
        "applicator": "Ống nhỏ giọt (dropper)",
        "label_language": ["Tiếng Việt", "Tiếng Anh"],
        "brand_mark_visible": "The Cocoon Original Vietnam – Cửa Hàng Chính Hãng"
      },
      "market_context": {
        "market": "vi-VN",
        "audience_language": "Vietnamese",
        "tone_target": "casual, spoken, natural"
      },
      "constraints_for_downstream": {
        "must_not_exaggerate": [
          "Không phóng đại hiệu quả trị mụn thành 'trị dứt điểm' hoặc 'chữa mụn'",
          "Không nâng mức 'hỗ trợ cải thiện' thành 'điều trị' hoặc 'chữa lành'",
          "Không tuyên bố tác dụng kháng khuẩn tuyệt đối từ tinh dầu tràm trà",
          "Không khẳng định tăng sinh collagen có kiểm chứng lâm sàng từ Centella CAST"
        ],
        "forbidden_claim_directions": [
          "Tuyên bố điều trị bệnh da liễu",
          "Cam kết kết quả trong thời gian cụ thể không có nguồn gốc từ nhãn hàng",
          "So sánh vượt trội so với sản phẩm khác nếu không có dữ liệu từ nguồn"
        ]
      },
      "uncertain_points": [
        "Dung tích/size sản phẩm không hiển thị rõ trên ảnh",
        "Nồng độ chính xác của Centella CAST, Ferulic Acid và chiết xuất bí đao không được công bố",
        "Thứ tự ưu tiên thành phần trong công thức đầy đủ chưa rõ",
        "Kết quả tăng sinh collagen từ Centella CAST chưa được xác nhận bởi nghiên cứu lâm sàng trong nguồn cung cấp"
      ]
    }
  }

{
    "intent_pack": {
      "primary_intent": "education_soft_product_support",
      "secondary_intent": "entertainment_relatable",
      "viewer_outcome_primary": "learn",
      "viewer_outcome_secondary": ["save", "comment"],
      "platform_hypothesis": {
        "primary_platform": "tiktok",
        "secondary_platforms": ["instagram_reels", "youtube_shorts"],
        "reasoning": "Đối tượng mục tiêu là người dùng trẻ, da dầu, quan tâm mụn – phân khúc rất active trên TikTok Vietnam. Nội dung giải thích thành phần (Niacinamide, BHA, NAG) phù hợp format skincare edu ngắn đang viral. Instagram Reels phù hợp aesthetic dropper bottle. YouTube Shorts phù hợp nếu muốn đẩy nội dung dài hơn giải thích ingredient."
      },
      "audience_segment_focus": {
        "core_segment": "Nữ 18–28 tuổi, da dầu, đang bị mụn ẩn hoặc vết thâm sau mụn, đã biết khái niệm serum và skincare cơ bản nhưng chưa rõ vai trò từng thành phần",
        "awareness_level": "solution_aware",
        "purchase_temperature": "warm"
      },
      "message_strategy": {
        "creative_angle": "Giải mã thành phần – 'Tại sao serum bí đao này lại hợp với da dầu mụn hơn bạn nghĩ': dẫn dắt bằng pain point quen thuộc (mụn ẩn, thâm lì), rồi giải thích ngắn gọn cơ chế từng thành phần chủ đạo (7% Niacinamide, 0.8% BHA, NAG 4%) theo ngôn ngữ casual – không lên lớp, không quảng cáo.",
        "product_visibility": "natural_use_first",
        "hard_sell_level": "low"
      },
      "tone_and_style_constraints": {
        "tone": "Thân thiện, gần gũi như bạn bè chia sẻ – không phải chuyên gia, không phải quảng cáo. Nói chuyện tự nhiên, thỉnh thoảng dùng từ lóng quen thuộc (ví dụ: 'da dầu bết', 'mụn ẩn li ti', 'thâm mãi không bay').",
        "style": "Skincare edu casual – dạng 'ingredient breakdown' ngắn, có thể kết hợp text overlay để highlight nồng độ thành phần. Hình ảnh ưu tiên dropper và texture serum trên da thật.",
        "avoid": [
          "Giọng điệu quảng cáo cứng, đọc tính năng như catalogue",
          "Từ ngữ y tế hoặc hứa hẹn điều trị",
          "So sánh với sản phẩm khác",
          "Cam kết timeline kết quả cụ thể",
          "Phong cách review dạng 'chấm điểm' cứng nhắc"
        ]
      },
      "non_goals": [
        "Không nhằm tạo chuyển đổi mua ngay (hard CTA) trong video này",
        "Không nhằm định vị thương hiệu Cocoon so với đối thủ",
        "Không nhằm giáo dục toàn bộ skincare routine – chỉ tập trung sản phẩm này",
        "Không tạo cảm giác sản phẩm chữa được mụn hoàn toàn"
      ],
      "success_criteria_for_next_steps": [
        "Hook trong 3 giây đầu phải chạm đúng pain point: mụn ẩn hoặc thâm – đo bằng retention rate giây 0–3",
        "Ít nhất một thành phần chính (Niacinamide hoặc BHA) được giải thích đủ để viewer hiểu lý do dùng – đo bằng comment hỏi thêm về thành phần",
        "Video không chứa bất kỳ claim nào vi phạm constraints_for_downstream của brief",
        "Tỷ lệ save cao hơn share – chỉ số phù hợp với nội dung edu có giá trị tham khảo lâu dài",
        "CTA cuối video là soft: gợi ý lưu/thử, không ép mua"
      ],
      "assumptions": [
        "Giả định đây là video organic hoặc seeding, không phải paid ad – nên ưu tiên edu thay vì hard-sell",
        "Giả định không có before/after ảnh thật của người dùng được cung cấp – tránh dùng dạng transformation claim",
        "Giả định audience đã quen với khái niệm Niacinamide (phổ biến trên TikTok VN) nên có thể đề cập trực tiếp không cần giải thích từ đầu",
        "Giả định Cocoon là thương hiệu nội địa có độ nhận diện tốt tại thị trường Việt – không cần giới thiệu brand từ đầu"
      ]
    }
  }

{
    "format_shortlist": {
      "selection_logic": {
        "primary_intent_used": "education_soft_product_support",
        "viewer_outcome_used": "learn",
        "hard_constraints_used": [
          "Không before/after transformation claim",
          "Không cam kết timeline kết quả",
          "Không so sánh sản phẩm khác",
          "Không ngôn ngữ điều trị/chữa bệnh",
          "Soft CTA only",
          "Tone casual bạn bè, không chuyên gia",
          "Chỉ dùng ngôn ngữ hỗ trợ/cải thiện"
        ]
      },
      "formats": [
        {
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "format_name_vi": "Giải mã thành phần – Serum này có gì bên trong?",
          "core_pattern": "Hook bằng pain point (mụn ẩn/thâm) → lần lượt highlight 2–3 thành phần chính với nồng độ (text overlay) → mỗi thành phần giải thích 1 câu vai trò → close-up texture/dropper → soft CTA lưu lại",
          "why_it_fits": "Đúng intent edu + soft product support. Audience solution_aware đã biết Niacinamide nên việc đi sâu vào nồng độ và combo thành phần tạo giá trị mới. Format phổ biến trên TikTok VN skincare, dễ save vì mang tính tham khảo lâu dài.",
          "best_for_outcome": ["learn", "save", "comment"],
          "hook_style_options": [
            "Pain point trực tiếp: 'Da dầu mụn ẩn mà chưa biết dùng serum nào?'",
            "Ingredient tease: '7% Niacinamide + 0.8% BHA trong cùng 1 lọ – combo này làm gì?'",
            "Curiosity gap: 'Serum bí đao mà có cả BHA lẫn NAG – nghe lạ nhưng hợp lý lắm'"
          ],
          "product_visibility_mode": "natural_use_first",
          "production_feasibility": {
            "score_1_to_5": 5,
            "why": "Chỉ cần 1 người nói + close-up sản phẩm + text overlay. Không cần diễn xuất phức tạp, không cần nhiều cảnh quay.",
            "requirements": ["1 talent/creator", "Lọ sản phẩm", "Ring light hoặc ánh sáng tự nhiên", "App edit có text overlay (CapCut)"],
            "risk_flags": ["Nếu giải thích quá dài hoặc quá kỹ thuật sẽ mất retention"]
          },
          "native_feel_score_1_to_5": 5,
          "compliance_safety_score_1_to_5": 5,
          "caption_overlay_pattern": "Text highlight nồng độ từng thành phần (ví dụ: '7% NIACINAMIDE' xuất hiện khi nhắc đến), bullet ngắn vai trò mỗi thành phần",
          "audio_style_hint": "Voiceover tự nhiên giọng nữ trẻ, nói chuyện casual như đang kể cho bạn nghe. Nhạc nền lo-fi nhẹ hoặc trending sound nhỏ.",
          "estimated_duration_range_sec": "30–45"
        },
        {
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "format_name_vi": "POV: Bước serum trong routine tối của mình",
          "core_pattern": "POV camera / first-person → rửa mặt xong → lấy serum ra → nhỏ giọt lên tay (close-up texture) → thoa lên mặt → voiceover giải thích ngắn tại sao chọn serum này → kết bằng soft CTA",
          "why_it_fits": "Kết hợp entertainment_relatable (routine đời thường) với edu (giải thích lý do chọn). Tận dụng được visual provable: dropper, texture lỏng không màu, thấm nhanh. Dạng POV routine rất native trên TikTok VN.",
          "best_for_outcome": ["save", "learn", "feel"],
          "hook_style_options": [
            "POV visual: Camera quay từ góc nhìn thứ nhất, tay cầm lọ serum – không cần nói gì 2 giây đầu",
            "Text hook: 'POV: bạn tìm được serum hợp da dầu mà không bết rít'",
            "Sound-driven: Dùng trending sound + text overlay pain point"
          ],
          "product_visibility_mode": "natural_use_first",
          "production_feasibility": {
            "score_1_to_5": 5,
            "why": "Quay POV góc nhìn thứ nhất rất đơn giản, chỉ cần điện thoại + sản phẩm + gương. Không cần lộ mặt nếu không muốn.",
            "requirements": ["Điện thoại có camera tốt", "Lọ sản phẩm", "Góc quay bàn skincare hoặc gương", "Ánh sáng ấm tự nhiên"],
            "risk_flags": ["Nếu chỉ quay routine mà không có voiceover edu thì thành pure aesthetic, mất yếu tố learn"]
          },
          "native_feel_score_1_to_5": 5,
          "compliance_safety_score_1_to_5": 5,
          "caption_overlay_pattern": "Text nhỏ góc trên: tên sản phẩm. Text highlight khi nhắc thành phần. Không cần nhiều text – ưu tiên visual.",
          "audio_style_hint": "Voiceover nhỏ nhẹ, intimate, kiểu nói thầm buổi tối. Hoặc dùng trending audio + text overlay thay voiceover.",
          "estimated_duration_range_sec": "20–35"
        },
        {
          "format_id": "F03_MYTH_VS_FACT",
          "format_name_vi": "Đúng hay Sai: Những hiểu lầm về serum cho da dầu",
          "core_pattern": "Đưa ra 2–3 câu hiểu lầm phổ biến (myth) về da dầu dùng serum → bẻ lại bằng fact ngắn gọn dựa trên thành phần → dẫn tự nhiên vào sản phẩm như ví dụ minh họa → soft CTA",
          "why_it_fits": "Format myth-bust kích thích comment (đồng ý/không đồng ý) và save (thông tin hữu ích). Phù hợp audience solution_aware – họ đã có kiến thức cơ bản nên sẽ thích tranh luận. Sản phẩm xuất hiện tự nhiên như ví dụ minh họa, không phải hero.",
          "best_for_outcome": ["comment", "learn", "save"],
          "hook_style_options": [
            "Provocative statement: 'Da dầu không cần dưỡng ẩm – ĐÚNG hay SAI?'",
            "List tease: '3 điều sai bét về serum cho da dầu mà ai cũng tin'",
            "Challenge: 'Bạn nghĩ BHA chỉ dùng để tẩy da chết? Sai rồi'"
          ],
          "product_visibility_mode": "subtle_background",
          "production_feasibility": {
            "score_1_to_5": 4,
            "why": "Cần chuẩn bị nội dung myth/fact chặt chẽ hơn format khác. Quay đơn giản nhưng script phải chính xác để không drift claim.",
            "requirements": ["1 talent/creator tự tin nói", "Script được review kỹ", "Text overlay rõ ràng ĐÚNG/SAI", "Lọ sản phẩm làm prop"],
            "risk_flags": ["Dễ bị drift sang ngôn ngữ 'trị mụn' khi bẻ myth", "Myth nếu chọn không khéo có thể gây hiểu lầm ngược"]
          },
          "native_feel_score_1_to_5": 4,
          "compliance_safety_score_1_to_5": 4,
          "caption_overlay_pattern": "Text lớn ĐÚNG/SAI với màu xanh/đỏ. Highlight thành phần và nồng độ khi giải thích fact.",
          "audio_style_hint": "Voiceover tự tin nhưng vẫn casual, kiểu bạn bè nói 'ê sai rồi nha'. Có thể dùng sound effect nhẹ khi chuyển đúng/sai.",
          "estimated_duration_range_sec": "30–50"
        },
        {
          "format_id": "F04_TEXTURE_ASMR_EDU",
          "format_name_vi": "Close-up texture serum + giải thích nhanh",
          "core_pattern": "Mở bằng close-up macro dropper nhỏ serum → texture chảy trên da → thoa thấm nhanh (ASMR visual) → voiceover hoặc text overlay giải thích ngắn 2–3 thành phần → soft CTA",
          "why_it_fits": "Kết hợp aesthetic/satisfying visual (entertainment_relatable) với edu ngắn. Tận dụng tối đa visual provable: dropper hổ phách, texture lỏng không màu, thấm nhanh không nhờn. Dạng texture video rất viral trên TikTok skincare VN.",
          "best_for_outcome": ["stop", "save", "learn"],
          "hook_style_options": [
            "Pure visual hook: Close-up macro dropper nhỏ giọt – không text, không voice 2 giây đầu",
            "Text overlay hook: 'Serum texture đẹp nhất mình từng dùng cho da dầu'",
            "ASMR sound: Âm thanh giọt serum rơi + nhạc nền minimal"
          ],
          "product_visibility_mode": "hero_moment",
          "production_feasibility": {
            "score_1_to_5": 4,
            "why": "Cần macro lens hoặc điện thoại camera tốt để quay close-up texture đẹp. Ánh sáng cần chuẩn hơn các format khác.",
            "requirements": ["Điện thoại camera tốt hoặc macro lens clip-on", "Ánh sáng tự nhiên mạnh hoặc ring light", "Lọ sản phẩm sạch", "Tay/da sạch cho close-up"],
            "risk_flags": ["Nếu quá thiên aesthetic mà thiếu edu thì không đạt intent learn", "Texture không màu có thể kém ấn tượng visual nếu không quay khéo"]
          },
          "native_feel_score_1_to_5": 4,
          "compliance_safety_score_1_to_5": 5,
          "caption_overlay_pattern": "Minimal text – chỉ highlight tên thành phần + nồng độ khi voiceover nhắc. Ưu tiên visual sạch.",
          "audio_style_hint": "ASMR-lite: âm thanh nhỏ giọt, thoa serum. Voiceover nhẹ nhàng hoặc chỉ dùng text. Nhạc nền ambient/minimal.",
          "estimated_duration_range_sec": "15–30"
        },
        {
          "format_id": "F05_DA_DAU_CHECKLIST",
          "format_name_vi": "Checklist: Serum cho da dầu cần có gì?",
          "core_pattern": "Đưa ra checklist 4–5 tiêu chí chọn serum cho da dầu mụn (ví dụ: có BHA, có Niacinamide, không cồn, texture nhẹ...) → tick từng mục → reveal sản phẩm đáp ứng tất cả ở cuối → soft CTA",
          "why_it_fits": "Dạng checklist/listicle rất dễ save và có giá trị tham khảo. Audience solution_aware sẽ thấy hữu ích vì đang tìm sản phẩm phù hợp. Sản phẩm xuất hiện cuối cùng một cách tự nhiên sau khi đã xây dựng tiêu chí.",
          "best_for_outcome": ["save", "learn", "click"],
          "hook_style_options": [
            "Question hook: 'Da dầu chọn serum – cần check những gì?'",
            "List tease: 'Serum cho da dầu mụn phải có 4 thứ này'",
            "Relatable hook: 'Chọn serum mà không biết check gì thì coi như đánh bạc với da'"
          ],
          "product_visibility_mode": "balanced",
          "production_feasibility": {
            "score_1_to_5": 5,
            "why": "Có thể làm hoàn toàn bằng text overlay + voiceover + cầm sản phẩm cuối. Rất đơn giản, không cần diễn xuất.",
            "requirements": ["1 talent hoặc chỉ cần tay + sản phẩm", "Text overlay rõ ràng (CapCut)", "Lọ sản phẩm cho cảnh reveal cuối"],
            "risk_flags": ["Nếu checklist quá generic thì mất giá trị edu", "Cần đảm bảo tiêu chí checklist đều dựa trên grounding, không thêm claim mới"]
          },
          "native_feel_score_1_to_5": 4,
          "compliance_safety_score_1_to_5": 4,
          "caption_overlay_pattern": "Checklist với checkbox tick animation từng mục. Text lớn rõ ràng cho mỗi tiêu chí. Reveal sản phẩm cuối với tên + nồng độ thành phần.",
          "audio_style_hint": "Voiceover casual đếm từng tiêu chí. Sound effect tick nhẹ. Nhạc nền upbeat nhẹ.",
          "estimated_duration_range_sec": "25–40"
        }
      ],
      "ranking": [
        {
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "rank": 1,
          "overall_score_100": 91,
          "score_breakdown": {
            "intent_fit_30": 29,
            "viewer_outcome_fit_20": 18,
            "native_feel_20": 19,
            "production_feasibility_15": 14,
            "compliance_safety_15": 11
          },
          "why_ranked_here": "Trùng khớp gần như hoàn hảo với primary intent (edu + soft product support) và creative angle đã chọn (giải mã thành phần). Nồng độ cụ thể (7% Niacinamide, 0.8% BHA, 4% NAG) tạo giá trị save rất cao. Dễ sản xuất, rủi ro claim thấp vì chỉ trình bày hard facts."
        },
        {
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "rank": 2,
          "overall_score_100": 85,
          "score_breakdown": {
            "intent_fit_30": 25,
            "viewer_outcome_fit_20": 17,
            "native_feel_20": 19,
            "production_feasibility_15": 14,
            "compliance_safety_15": 10
          },
          "why_ranked_here": "Cân bằng tốt giữa entertainment_relatable và edu. Native feel rất cao vì POV routine là format phổ biến nhất trên TikTok VN skincare. Tận dụng tốt visual provable (dropper, texture). Hơi thiên entertainment hơn edu nên intent fit thấp hơn F01."
        },
        {
          "format_id": "F05_DA_DAU_CHECKLIST",
          "rank": 3,
          "overall_score_100": 80,
          "score_breakdown": {
            "intent_fit_30": 26,
            "viewer_outcome_fit_20": 17,
            "native_feel_20": 15,
            "production_feasibility_15": 14,
            "compliance_safety_15": 8
          },
          "why_ranked_here": "Rất mạnh về save outcome và edu intent. Dễ sản xuất. Tuy nhiên dạng checklist có thể hơi 'template' và kém native feel hơn F01/F02. Cần cẩn thận để tiêu chí checklist không drift thành claim mới ngoài grounding."
        },
        {
          "format_id": "F03_MYTH_VS_FACT",
          "rank": 4,
          "overall_score_100": 76,
          "score_breakdown": {
            "intent_fit_30": 24,
            "viewer_outcome_fit_20": 16,
            "native_feel_20": 14,
            "production_feasibility_15": 12,
            "compliance_safety_15": 10
          },
          "why_ranked_here": "Tốt cho comment engagement và edu. Tuy nhiên rủi ro claim drift cao hơn khi phải bẻ myth – dễ vô tình dùng ngôn ngữ mạnh. Cần script review kỹ hơn. Native feel ổn nhưng không bằng ingredient breakdown hay POV routine trên TikTok VN."
        },
        {
          "format_id": "F04_TEXTURE_ASMR_EDU",
          "rank": 5,
          "overall_score_100": 72,
          "score_breakdown": {
            "intent_fit_30": 20,
            "viewer_outcome_fit_20": 14,
            "native_feel_20": 16,
            "production_feasibility_15": 12,
            "compliance_safety_15": 10
          },
          "why_ranked_here": "Mạnh về stop (scroll-stopping visual) và native feel. Tuy nhiên thiên aesthetic hơn edu – yếu hơn về primary intent education_soft_product_support. Texture không màu của sản phẩm có thể hạn chế visual impact. Phù hợp hơn làm content bổ trợ hơn là video chính."
        }
      ],
      "recommended_top_2_for_step5": [
        {
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "reason": "Trùng khớp tốt nhất với creative angle đã chọn (giải mã thành phần), tận dụng hard facts về nồng độ (7% Niacinamide, 0.8% BHA, 4% NAG), dễ sản xuất, rủi ro compliance thấp, và tối ưu cho outcome learn + save."
        },
        {
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "reason": "Bổ trợ tốt cho F01 – thêm yếu tố entertainment_relatable và tận dụng visual provable (texture, dropper). Nếu F01 thiên edu thì F02 cân bằng lại bằng cảm giác đời thường, gần gũi. Có thể dùng song song hoặc chọn 1 tùy creator style."
        }
      ],
      "formats_to_avoid_now": [
        {
          "format_name_vi": "Before/After Transformation",
          "why_not_now": "Không có ảnh before/after thật được cung cấp. Tuyên bố transformation vi phạm constraints_for_downstream và grounding. Rủi ro compliance rất cao."
        },
        {
          "format_name_vi": "Bác sĩ / Chuyên gia review",
          "why_not_now": "Tone yêu cầu là casual bạn bè, không phải chuyên gia. Dạng dermatologist authority performance bị penalize theo selection rules. Không phù hợp với audience_segment (trẻ, casual) và intent (edu soft, không phải medical authority)."
        },
        {
          "format_name_vi": "So sánh sản phẩm / Battle format",
          "why_not_now": "Không có dữ liệu so sánh với sản phẩm khác từ nguồn. Mọi comparative claim đều vi phạm forbidden_claim_directions. Non-goal rõ ràng: không định vị vs đối thủ."
        },
        {
          "format_name_vi": "Challenge / Thử thách X ngày",
          "why_not_now": "Không có dữ liệu timeline kết quả từ nhãn hàng. Cam kết hiệu quả sau X ngày vi phạm efficacy_timeline trong risky_or_regulated. Dễ tạo kỳ vọng sai."
        }
      ],
      "assumptions": [
        "Video organic/seeding, không phải paid ad – ưu tiên edu value hơn conversion",
        "1 creator/talent duy nhất, không cần multi-actor",
        "Quay bằng điện thoại, edit bằng CapCut hoặc tương đương",
        "Audience đã quen Niacinamide và BHA ở mức cơ bản",
        "Cocoon đã có độ nhận diện – không cần giới thiệu brand từ đầu",
        "Không có before/after assets hoặc UGC có sẵn",
        "Đăng trên TikTok là primary, có thể repurpose sang Reels/Shorts"
      ]
    }
  }