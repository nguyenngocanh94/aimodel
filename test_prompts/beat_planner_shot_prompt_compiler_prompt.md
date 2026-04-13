# Step 7 Prompt — Beat Planner + Shot Prompt Compiler (Full JSON Included)

```text
You are the "Beat Planner + Shot Prompt Compiler" node.

Task:
Use the provided `brief`, `intent_pack`, `grounding`, `format_shortlist`, and `hook_pack`
to produce a production-ready plan for one short-form video.

You must:
1) Select ONE primary hook from hook_pack selection recommendation.
2) Build a concise beat structure that maximizes 0-3s retention and save-worthy education.
3) Compile shot-level prompts that are feasible for current short-video generation/edit workflows.
4) Keep all language and claims compliant with grounding constraints.

Do NOT:
- invent new product claims
- use medical/treatment framing
- add competitor comparison
- add hard-sell CTA

Hard constraints:
- Primary intent: education_soft_product_support
- Secondary intent: entertainment_relatable
- Audience language: Vietnamese (casual, spoken, natural)
- Render prompt strategy: English-first visual prompt is allowed; audience-facing text remains Vietnamese
- Soft CTA only
- Respect forbidden_phrasing_vi and downstream_guardrails

Output requirements:
- Return valid JSON only (no markdown, no explanation).
- Use exact schema below.
- Keep durations realistic for 20-40s total.
- Every beat must introduce one new reason to keep watching.
- Keep one primary subject focus and continuity consistency.

Return exactly this schema:
{
  "production_pack": {
    "selected_hook": {
      "hook_id": "string",
      "hook_text_vi": "string",
      "why_selected": "string"
    },
    "video_spec": {
      "target_platform": "tiktok | instagram_reels | youtube_shorts",
      "target_duration_sec": 0,
      "aspect_ratio": "9:16",
      "style_mode": "ingredient_breakdown | pov_routine_hybrid",
      "primary_outcome": "learn | save | comment"
    },
    "continuity_tokens": {
      "subject_profile": "string",
      "wardrobe": "string",
      "environment": "string",
      "lighting": "string",
      "product_identity_lock": [
        "string"
      ],
      "camera_behavior_lock": [
        "string"
      ]
    },
    "beats": [
      {
        "beat_id": "B1",
        "purpose": "hook | context | ingredient_explain | usage_demo | credibility_softener | cta_soft",
        "duration_sec": 0,
        "new_reason_to_watch": "string",
        "narrative_text_vi": "string",
        "compliance_note": "string"
      }
    ],
    "shots": [
      {
        "shot_id": "S1",
        "beat_id": "B1",
        "duration_sec": 0,
        "camera": "string",
        "action": "string",
        "visual_prompt_en": "string",
        "on_screen_text_vi": "string",
        "voiceover_vi": "string|null",
        "sfx_music_hint": "string",
        "product_visibility": "subtle_background | natural_use_first | balanced | hero_moment",
        "claim_guardrail": "string",
        "fallback_if_generation_fails": "string"
      }
    ],
    "caption_package": {
      "caption_vi": "string",
      "pinned_comment_vi": "string",
      "hashtags_vi": ["string"]
    },
    "quality_checks": {
      "objective_fit_checklist": ["string"],
      "compliance_checklist": ["string"],
      "continuity_checklist": ["string"]
    }
  }
}

Planning rules:
1) Use top-ranked format logic (F01 first) with optional F02 flavor for relatability.
2) Favor hook F01_H02 as default, F02_H01 as backup.
3) Include ingredient concentrations only when grounded: 7% Niacinamide, 0.8% BHA, 4% NAG.
4) Include a soft disclaimer naturally, not in legalistic tone.
5) Avoid tiny pack text dependency in visuals.
6) Keep shot actions easy: close-up dropper, hand application, mirror/vanity routine, simple face close-up.
7) CTA must be soft (save/comment/ask), not purchase pressure.

Inputs:
[PASTE_BRIEF_JSON]
[PASTE_INTENT_PACK_JSON]
[PASTE_GROUNDING_JSON]
[PASTE_FORMAT_SHORTLIST_JSON]
[PASTE_HOOK_PACK_JSON]
```

## Input JSON Blocks (Full)

### 1) `brief` JSON
```json
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
        "skin_type": [
          "Da dầu"
        ],
        "concerns": [
          "Mụn ẩn",
          "Mụn trứng cá",
          "Mụn đầu đen",
          "Vết thâm",
          "Lỗ chân lông to",
          "Da thừa dầu"
        ],
        "ideal_for": [
          "Da dầu, da có mụn ẩn, mụn trứng cá cần sản phẩm chăm sóc chuyên biệt để phục hồi nhanh chóng"
        ]
      },
      "usage": {
        "how_to_use": "Lấy vài giọt tinh chất vào lòng bàn tay, xoa đều và mát-xa lên da mặt sạch, tránh vùng mắt",
        "frequency": "Sử dụng sáng và tối",
        "amount": "4–6 giọt dùng cho toàn da mặt",
        "warnings": [
          "Tránh vùng mắt"
        ]
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
        "label_language": [
          "Tiếng Việt",
          "Tiếng Anh"
        ],
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
```

### 2) `intent_pack` JSON
```json
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
```

### 3) `grounding` JSON
```json
{
    "grounding": {
      "product_identity": {
        "brand": "The Cocoon Original Vietnam",
        "product_name_full": "Tinh chất bí đao N7 - Winter Melon Serum N7",
        "category": "Serum dưỡng da mặt",
        "size": null
      },
      "hard_facts": [
        {
          "fact": "Chứa 7% Niacinamide (Vitamin B3)",
          "source_anchor": "brief.ingredients_highlights"
        },
        {
          "fact": "Chứa 4% Acetyl Glucosamine (NAG)",
          "source_anchor": "brief.ingredients_highlights"
        },
        {
          "fact": "Chứa 0.8% Salicylic Acid (BHA)",
          "source_anchor": "brief.ingredients_highlights"
        },
        {
          "fact": "Có chứa chiết xuất bí đao (nồng độ không công bố)",
          "source_anchor": "brief.ingredients_highlights"
        },
        {
          "fact": "Có chứa Centella CAST (TECA từ rau má, nồng độ không công bố)",
          "source_anchor": "brief.ingredients_highlights"
        },
        {
          "fact": "Có chứa tinh dầu tràm trà (nồng độ không công bố)",
          "source_anchor": "brief.ingredients_highlights"
        },
        {
          "fact": "Có chứa Ferulic Acid (nồng độ không công bố)",
          "source_anchor": "brief.ingredients_highlights"
        },
        {
          "fact": "Không chứa cồn, không sulfate, không dầu khoáng, không paraben",
          "source_anchor": "brief.free_from"
        },
        {
          "fact": "Dạng dung dịch lỏng, sánh nhẹ, không màu, mùi tinh dầu tràm trà thoang thoảng",
          "source_anchor": "brief.sensory"
        },
        {
          "fact": "Đóng gói lọ thủy tinh màu nâu hổ phách, dạng dropper",
          "source_anchor": "brief.visual_facts"
        },
        {
          "fact": "Dùng sáng và tối, 4–6 giọt cho toàn mặt, tránh vùng mắt",
          "source_anchor": "brief.usage"
        },
        {
          "fact": "Phù hợp cho da dầu, da có mụn ẩn, mụn trứng cá",
          "source_anchor": "brief.claims_verbatim"
        }
      ],
      "allowed_phrasing_vi": [
        {
          "intent": "benefit",
          "phrase": "Hỗ trợ cải thiện mụn ẩn và mụn đầu đen"
        },
        {
          "intent": "benefit",
          "phrase": "Giúp kiểm soát dầu thừa"
        },
        {
          "intent": "benefit",
          "phrase": "Làm mờ vết thâm sau mụn"
        },
        {
          "intent": "benefit",
          "phrase": "Cung cấp độ ẩm cho da"
        },
        {
          "intent": "benefit",
          "phrase": "Giúp thông thoáng lỗ chân lông"
        },
        {
          "intent": "benefit",
          "phrase": "Làm dịu da, giảm các vết đỏ"
        },
        {
          "intent": "benefit",
          "phrase": "Giúp làm giảm sự xuất hiện của lỗ chân lông to"
        },
        {
          "intent": "ingredient_role",
          "phrase": "7% Niacinamide hỗ trợ cải thiện mụn và giảm sự xuất hiện của lỗ chân lông to"
        },
        {
          "intent": "ingredient_role",
          "phrase": "0.8% BHA (Salicylic Acid) thấm vào lỗ chân lông, giúp thông thoáng và cải thiện mụn"
        },
        {
          "intent": "ingredient_role",
          "phrase": "4% NAG giúp cải thiện độ ẩm và hỗ trợ làm sáng da, đặc biệt khi kết hợp với Niacinamide"
        },
        {
          "intent": "ingredient_role",
          "phrase": "Chiết xuất bí đao có đặc tính làm mát da"
        },
        {
          "intent": "ingredient_role",
          "phrase": "Tinh dầu tràm trà có mùi thơm ấm áp, hỗ trợ ngừa mụn trứng cá"
        },
        {
          "intent": "ingredient_role",
          "phrase": "Ferulic Acid giúp trung hòa gốc tự do và hỗ trợ làm đều màu vết thâm"
        },
        {
          "intent": "ingredient_role",
          "phrase": "Centella CAST (rau má) liên quan đến việc hỗ trợ tái tạo da"
        },
        {
          "intent": "usage",
          "phrase": "Dùng sáng và tối, lấy 4–6 giọt xoa nhẹ lên da mặt đã rửa sạch, tránh vùng mắt"
        },
        {
          "intent": "sensory",
          "phrase": "Kết cấu lỏng nhẹ, thấm nhanh, không nhờn rít"
        },
        {
          "intent": "sensory",
          "phrase": "Mùi tinh dầu tràm trà thoang thoảng, dễ chịu"
        },
        {
          "intent": "suitability",
          "phrase": "Phù hợp với da dầu, da có mụn ẩn và vết thâm"
        },
        {
          "intent": "suitability",
          "phrase": "BHA 0.8% ở nồng độ dịu nhẹ, phù hợp cho da nhạy cảm theo thông tin từ nhãn hàng"
        }
      ],
      "risky_or_regulated": [
        {
          "topic": "anti_acne_claim",
          "why_risky": "Các cụm từ 'giảm mụn', 'trị mụn', 'chữa mụn' hoặc 'ngừa mụn hoàn toàn' mang hàm ý điều trị bệnh lý da liễu, không được phép trong nội dung quảng cáo mỹ phẩm tại Việt Nam.",
          "safe_reframe_vi": "Dùng: 'hỗ trợ cải thiện tình trạng mụn', 'giúp mụn ẩn xuất hiện ít hơn', 'hỗ trợ ngừa mụn trứng cá'"
        },
        {
          "topic": "collagen_claim",
          "why_risky": "Nguồn ghi Centella CAST 'giúp tăng sinh collagen cho làn da' nhưng không có bằng chứng lâm sàng được xác nhận trong brief. Không thể tuyên bố tăng sinh collagen có kiểm chứng.",
          "safe_reframe_vi": "Dùng: 'Rau má chứa các hợp chất liên quan đến quá trình tái tạo da' hoặc 'Centella hỗ trợ phục hồi da'"
        },
        {
          "topic": "uv_protection",
          "why_risky": "Ferulic Acid được mô tả là 'ngăn ngừa tác hại của tia cực tím' – nếu diễn đạt không cẩn thận có thể bị hiểu là tác dụng chống nắng (sunscreen), vốn yêu cầu chứng nhận SPF riêng.",
          "safe_reframe_vi": "Dùng: 'Ferulic Acid giúp trung hòa gốc tự do, hỗ trợ bảo vệ da khỏi tác động môi trường' – KHÔNG dùng 'chống nắng' hoặc 'bảo vệ tia UV'"
        },
        {
          "topic": "sensitive_skin_safety",
          "why_risky": "BHA 0.8% được mô tả là 'dịu nhẹ và an toàn cho da nhạy cảm' theo nguồn nhãn hàng, nhưng không có bằng chứng lâm sàng độc lập xác nhận trong brief. Không thể khẳng định tuyệt đối an toàn cho mọi da nhạy cảm.",
          "safe_reframe_vi": "Dùng: 'BHA 0.8% ở nồng độ dịu nhẹ, theo nhãn hàng phù hợp cho cả da nhạy cảm – khuyến khích patch test trước'"
        },
        {
          "topic": "efficacy_timeline",
          "why_risky": "Không có dữ liệu timeline kết quả từ nhãn hàng. Cam kết kết quả trong thời gian cụ thể (ví dụ: '2 tuần mờ thâm') là không có cơ sở và vi phạm quy định.",
          "safe_reframe_vi": "Không đề cập timeline. Nếu cần: 'Kết quả có thể khác nhau tùy cơ địa mỗi người'"
        },
        {
          "topic": "comparative_claim",
          "why_risky": "Không có dữ liệu so sánh với sản phẩm khác từ nguồn. Mọi tuyên bố vượt trội so với đối thủ đều không có cơ sở.",
          "safe_reframe_vi": "Không so sánh. Tập trung vào đặc điểm công thức của sản phẩm này."
        }
      ],
      "forbidden_phrasing_vi": [
        "Trị mụn dứt điểm",
        "Chữa mụn",
        "Điều trị mụn trứng cá",
        "Trị thâm hoàn toàn",
        "Chữa lành da",
        "Tăng sinh collagen (không có dẫn chứng lâm sàng)",
        "Chống nắng",
        "Bảo vệ tia UV / SPF",
        "Kháng khuẩn tuyệt đối",
        "100% an toàn cho da nhạy cảm",
        "Cam kết hiệu quả sau X ngày / X tuần",
        "Tốt hơn [tên sản phẩm khác]",
        "Số 1 cho da dầu mụn",
        "Điều trị bệnh da liễu",
        "Hỗ trợ cải thiện → nâng thành → điều trị / chữa lành"
      ],
      "visually_provable_details": [
        "Lọ thủy tinh màu nâu hổ phách với ống nhỏ giọt (dropper)",
        "Nhãn sản phẩm song ngữ Tiếng Việt – Tiếng Anh",
        "Logo và tên thương hiệu: The Cocoon Original Vietnam",
        "Kết cấu serum lỏng, không màu khi nhỏ giọt ra tay",
        "Texture thấm nhanh khi thoa lên da (demonstrable on camera)",
        "Số giọt sử dụng: 4–6 giọt mỗi lần"
      ],
      "mandatory_disclaimers_soft_vi": [
        "Kết quả có thể khác nhau tùy cơ địa và tình trạng da mỗi người.",
        "Nên patch test trước khi dùng, đặc biệt với da nhạy cảm.",
        "Sản phẩm hỗ trợ chăm sóc da, không thay thế chỉ định y tế.",
        "Tránh tiếp xúc trực tiếp với vùng mắt."
      ],
      "uncertain_or_needs_evidence": [
        "Dung tích/size sản phẩm chưa được công bố rõ trong brief.",
        "Nồng độ chính xác của Centella CAST, Ferulic Acid và chiết xuất bí đao chưa được công bố.",
        "Hiệu quả tăng sinh collagen từ Centella CAST chưa có xác nhận lâm sàng trong nguồn cung cấp.",
        "Thứ tự ưu tiên thành phần trong công thức đầy đủ chưa rõ.",
        "Mức độ 'an toàn cho da nhạy cảm' của BHA 0.8% chưa có bằng chứng lâm sàng độc lập.",
        "Hiệu quả kháng khuẩn thực tế của tinh dầu tràm trà ở nồng độ trong công thức chưa được xác nhận."
      ],
      "downstream_guardrails": {
        "must_keep_tone": "Thân thiện, gần gũi như bạn bè chia sẻ – casual skincare edu, không giọng chuyên gia, không giọng quảng cáo catalogue. Ngôn ngữ tự nhiên, có thể dùng từ lóng quen thuộc với người dùng trẻ Việt Nam.",
        "must_avoid": [
          "Bất kỳ từ nào mang nghĩa điều trị/chữa bệnh da liễu",
          "Cam kết timeline kết quả cụ thể không có nguồn từ nhãn hàng",
          "So sánh với sản phẩm hoặc thương hiệu khác",
          "Tuyên bố collagen có kiểm chứng lâm sàng",
          "Tuyên bố chống nắng hoặc bảo vệ UV",
          "Hard CTA ép mua ngay",
          "Before/after transformation claim không có ảnh thật được cung cấp",
          "Nâng cấp 'hỗ trợ cải thiện' thành 'điều trị' hoặc 'chữa lành'"
        ],
        "claim_strength_rule": "Chỉ dùng ngôn ngữ hỗ trợ/cải thiện (support/improve). Không dùng ngôn ngữ điều trị/chữa khỏi/đảm bảo kết quả. Với thành phần không công bố nồng độ, chỉ mô tả vai trò chức năng chung, không phóng đại.",
        "cta_rule": "CTA cuối video phải là soft CTA: gợi ý lưu video, thử sản phẩm, hoặc hỏi thêm trong comment. Không dùng CTA ép mua ngay, không dùng ngôn ngữ khan hiếm/urgency giả tạo."
      }
    }
  }
```

### 4) `format_shortlist` JSON
```json
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
        }
      ],
      "ranking": [
        {
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "rank": 1,
          "overall_score_100": 91
        },
        {
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "rank": 2,
          "overall_score_100": 85
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
      ]
    }
  }
```

### 5) `hook_pack` JSON
```json
{
    "hook_pack": {
      "selected_formats": ["F01_INGREDIENT_BREAKDOWN", "F02_POV_ROUTINE_MOMENT"],
      "hooks": [
        {
          "hook_id": "F01_H01",
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "hook_text_vi": "Da dầu mụn ẩn – bạn đã biết combo 7% Niacinamide và 0.8% BHA chưa?"
        },
        {
          "hook_id": "F01_H02",
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "hook_text_vi": "7% Niacinamide, 0.8% BHA, 4% NAG – combo này trong một lọ serum bí đao?"
        },
        {
          "hook_id": "F01_H03",
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "hook_text_vi": "Thâm mụn mãi không mờ – thử kiểm tra serum bạn đang dùng có thiếu gì không"
        },
        {
          "hook_id": "F01_H04",
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "hook_text_vi": "NAG 4% là gì mà kết hợp với Niacinamide lại hay vậy?"
        },
        {
          "hook_id": "F01_H05",
          "format_id": "F01_INGREDIENT_BREAKDOWN",
          "hook_text_vi": "Lỗ chân lông to, dầu thừa – mình giải thích serum này hỗ trợ cải thiện kiểu gì"
        },
        {
          "hook_id": "F02_H01",
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "hook_text_vi": "POV: bước serum tối nay của đứa da dầu mụn ẩn"
        },
        {
          "hook_id": "F02_H02",
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "hook_text_vi": "Tối nào cũng dùng – serum bí đao mà da dầu thoa không bết rít"
        },
        {
          "hook_id": "F02_H03",
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "hook_text_vi": "Không voiceover, chỉ quay tay nhỏ serum lên da – xem texture thật nhé"
        },
        {
          "hook_id": "F02_H04",
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "hook_text_vi": "Da dầu mà dùng serum – nghe ngược đời nhưng mình kể vì sao"
        },
        {
          "hook_id": "F02_H05",
          "format_id": "F02_POV_ROUTINE_MOMENT",
          "hook_text_vi": "Rửa mặt xong – bước tiếp theo của mình là lọ này"
        }
      ],
      "selection_recommendation": {
        "best_primary_hook_id": "F01_H02",
        "best_backup_hook_id": "F02_H01",
        "why": "F01_H02 mở bằng 3 con số nồng độ cụ thể – tạo giá trị edu ngay giây đầu, rất khác biệt so với quảng cáo serum thông thường trên TikTok VN. Audience solution_aware sẽ nhận ra ngay đây là nội dung đáng xem. F02_H01 là backup vì POV routine tối có native feel cực cao, hoạt động tốt nếu audience phản hồi yếu với dạng số liệu – chuyển sang cảm xúc relatable thay thế."
      }
    }
  }
```
