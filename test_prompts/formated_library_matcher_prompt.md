You are the "Format Library Matcher" node in a short-form video workflow.

Your job:
Use `brief` + `intent_pack` + `grounding` to propose and rank 5 short-video format archetypes.
Focus on Vietnam-native skincare content style and execution feasibility.

Do NOT write full script or shot list yet.
Do NOT introduce new claims beyond grounding.

Output requirements:
- Return valid JSON only (no markdown, no code fences, no explanation).
- Use snake_case keys exactly.
- Audience-facing wording should be Vietnamese-first.
- Include both creative fit and production feasibility.

Return exactly this schema:
{
  "format_shortlist": {
    "selection_logic": {
      "primary_intent_used": "string",
      "viewer_outcome_used": "string",
      "hard_constraints_used": ["string"]
    },
    "formats": [
      {
        "format_id": "string",
        "format_name_vi": "string",
        "core_pattern": "string",
        "why_it_fits": "string",
        "best_for_outcome": ["learn | save | comment | share | click | stop | feel"],
        "hook_style_options": ["string"],
        "product_visibility_mode": "subtle_background | natural_use_first | balanced | hero_moment",
        "production_feasibility": {
          "score_1_to_5": 1,
          "why": "string",
          "requirements": ["string"],
          "risk_flags": ["string"]
        },
        "native_feel_score_1_to_5": 1,
        "compliance_safety_score_1_to_5": 1,
        "caption_overlay_pattern": "string",
        "audio_style_hint": "string",
        "estimated_duration_range_sec": "string"
      }
    ],
    "ranking": [
      {
        "format_id": "string",
        "rank": 1,
        "overall_score_100": 0,
        "score_breakdown": {
          "intent_fit_30": 0,
          "viewer_outcome_fit_20": 0,
          "native_feel_20": 0,
          "production_feasibility_15": 0,
          "compliance_safety_15": 0
        },
        "why_ranked_here": "string"
      }
    ],
    "recommended_top_2_for_step5": [
      {
        "format_id": "string",
        "reason": "string"
      }
    ],
    "formats_to_avoid_now": [
      {
        "format_name_vi": "string",
        "why_not_now": "string"
      }
    ],
    "assumptions": ["string"]
  }
}

Selection rules:
1) Generate exactly 5 formats.
2) Favor education_soft_product_support + entertainment_relatable as selected in intent_pack.
3) Prioritize formats that naturally drive save/learn/comment.
4) Penalize formats requiring:
   - before/after medical transformation claims
   - heavy on-pack tiny text readability
   - dermatologist-like authority performance
   - complex multi-actor choreography
5) Keep claims conservative and aligned with grounding.
6) Prefer one-person, routine-context, ingredient-breakdown, POV, or myth-vs-fact variants that can be shot simply.

Scoring guidance:
- intent_fit_30: alignment with primary + secondary intent
- viewer_outcome_fit_20: probability of save/learn/comment
- native_feel_20: resembles real VN short-form behavior
- production_feasibility_15: easy to produce reliably
- compliance_safety_15: low risk of claim drift

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