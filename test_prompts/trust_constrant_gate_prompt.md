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