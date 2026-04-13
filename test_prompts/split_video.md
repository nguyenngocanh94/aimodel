You are the "Video API Render Spec Compiler" node.

Goal:
Convert `production_pack` into an API-ready render plan where EVERY clip is under 6 seconds.
Target clip duration: 3–5 seconds each.

Hard constraints:
- Every generated video clip must be < 6.0s
- Keep continuity across all clips (same subject, wardrobe, lighting, product identity)
- No text rendered inside generated video (all text will be overlayed in edit stage)
- No medical or exaggerated claim language in any prompt text
- Keep visual actions simple and reliable for current video models

Output requirements:
- Return valid JSON only (no markdown, no explanation)
- Use exact schema below
- Split long shots into multiple micro-clips if needed

Return exactly:
{
  "render_pack": {
    "render_settings": {
      "aspect_ratio": "9:16",
      "resolution": "1080x1920",
      "fps": 24,
      "duration_rule_sec": "<6",
      "target_duration_range_sec": "3-5",
      "num_variants_per_clip": 2
    },
    "global_consistency": {
      "subject_lock": "string",
      "wardrobe_lock": "string",
      "environment_lock": "string",
      "lighting_lock": "string",
      "product_lock": "string",
      "camera_lock": "string"
    },
    "negative_prompt_en": "string",
    "clips": [
      {
        "clip_id": "C01",
        "source_shot_id": "S1",
        "duration_sec": 4,
        "prompt_en": "string",
        "camera_move": "static | slow_push | handheld_light | tilt_light",
        "action_focus": "string",
        "continuity_tags": ["string"],
        "safety_notes": ["string"]
      }
    ],
    "assembly_map": [
      {
        "clip_id": "C01",
        "target_beat_id": "B1",
        "order_index": 1
      }
    ],
    "qc_gate": {
      "must_pass": [
        "no distorted hands/fingers",
        "no facial deformation",
        "product shape stays amber dropper bottle",
        "no random text artifacts",
        "stable lighting continuity"
      ],
      "reject_if": [
        "clip duration >= 6s",
        "extra people appear",
        "logo/text becomes unreadable gibberish focus",
        "heavy flicker or frame jitter"
      ]
    }
  }
}

Rules:
1) Rewrite production_pack shots into micro-clips under 6 seconds.
2) If a source shot is 6s or longer, split into multiple clips (e.g., S3a/S3b).
3) Keep prompts visual and concrete:
   - subject, camera framing, action, environment, light
4) Avoid any dependency on readable product label text.
5) Mention product by physical traits only (amber glass dropper bottle, clear liquid serum).
6) Keep one person only, no crowds, no complex choreography.
7) Do not include overlays/captions in generated clips; those are post-production.

Input:
{
    "production_pack": {
      "selected_hook": {
        "hook_id": "F01_H02",
        "hook_text_vi": "7% Niacinamide, 0.8% BHA, 4% NAG – combo này trong một lọ serum bí đao?",
        "why_selected": "Mở bằng 3 con số nồng độ cụ thể tạo curiosity gap mạnh ngay giây đầu. Audience solution_aware đã quen Niacinamide nên nhận ra giá trị edu ngay. Khác biệt rõ so với quảng cáo serum generic trên TikTok VN. Kích thích save vì mang tính tham khảo thành phần."
      },
      "video_spec": {
        "target_platform": "tiktok",
        "target_duration_sec": 35,
        "aspect_ratio": "9:16",
        "style_mode": "ingredient_breakdown",
        "primary_outcome": "learn"
      },
      "continuity_tokens": {
        "subject_profile": "Nữ trẻ 20–26, da sáng tự nhiên, tóc buộc gọn hoặc kẹp lên – tạo cảm giác đang ở nhà skincare routine",
        "wardrobe": "Áo thun trơn màu nhạt hoặc trắng, không logo – giữ focus vào sản phẩm và da mặt",
        "environment": "Góc bàn skincare tối giản, nền sáng hoặc trắng kem, có gương nhỏ hoặc khay đựng mỹ phẩm – aesthetic nhưng đời thường",
        "lighting": "Ring light trắng ấm hoặc ánh sáng tự nhiên cửa sổ – da rõ ràng, không harsh shadow, không filter nặng",
        "product_identity_lock": [
          "Lọ thủy tinh nâu hổ phách với dropper",
          "Nhãn song ngữ Tiếng Việt – Tiếng Anh rõ nét",
          "Logo The Cocoon Original Vietnam hiển thị tự nhiên khi cầm lọ",
          "Serum lỏng không màu khi nhỏ giọt"
        ],
        "camera_behavior_lock": [
          "Chủ yếu close-up và medium close-up – không wide shot",
          "Chuyển cảnh bằng cut nhanh hoặc zoom nhẹ – không transition phức tạp",
          "Giữ lọ sản phẩm luôn trong khung hình khi nhắc thành phần",
          "Không lật camera hoặc đổi góc đột ngột"
        ]
      },
      "beats": [
        {
          "beat_id": "B1",
          "purpose": "hook",
          "duration_sec": 4,
          "new_reason_to_watch": "3 con số nồng độ cụ thể xuất hiện ngay – tạo curiosity: combo này làm gì trong 1 lọ?",
          "narrative_text_vi": "7% Niacinamide, 0.8% BHA, 4% NAG – combo này trong một lọ serum bí đao?",
          "compliance_note": "Chỉ nêu nồng độ đã xác nhận từ brief. Không claim hiệu quả trong hook."
        },
        {
          "beat_id": "B2",
          "purpose": "context",
          "duration_sec": 5,
          "new_reason_to_watch": "Kết nối pain point cá nhân – viewer thấy mình trong câu chuyện",
          "narrative_text_vi": "Da dầu, mụn ẩn li ti, thâm mãi không mờ – quen không? Mình cũng vậy, nên mình tìm hiểu kỹ serum này.",
          "compliance_note": "Nói từ góc nhìn cá nhân, không tuyên bố sản phẩm chữa được vấn đề. Dùng 'tìm hiểu' thay vì 'dùng thấy hiệu quả'."
        },
        {
          "beat_id": "B3",
          "purpose": "ingredient_explain",
          "duration_sec": 8,
          "new_reason_to_watch": "Giải thích cơ chế Niacinamide 7% và BHA 0.8% – kiến thức cụ thể viewer có thể áp dụng",
          "narrative_text_vi": "7% Niacinamide hỗ trợ cải thiện mụn và giảm lỗ chân lông to. 0.8% BHA thấm vào lỗ chân lông, giúp thông thoáng – mà nồng độ này dịu nhẹ nha.",
          "compliance_note": "Dùng đúng allowed_phrasing. BHA dịu nhẹ theo nhãn hàng – không khẳng định an toàn tuyệt đối cho da nhạy cảm."
        },
        {
          "beat_id": "B4",
          "purpose": "ingredient_explain",
          "duration_sec": 6,
          "new_reason_to_watch": "NAG 4% – thành phần ít người biết, tạo thêm giá trị mới cho viewer",
          "narrative_text_vi": "Còn NAG 4% – cái tên nghe lạ nhưng nó giúp cải thiện độ ẩm và hỗ trợ sáng da, nhất là khi đi chung với Niacinamide.",
          "compliance_note": "Dùng đúng allowed_phrasing cho NAG. Không phóng đại thành 'trắng da' hay 'trị thâm dứt điểm'."
        },
        {
          "beat_id": "B5",
          "purpose": "usage_demo",
          "duration_sec": 6,
          "new_reason_to_watch": "Visual texture thật – viewer thấy serum lỏng nhẹ thấm nhanh, giải tỏa lo ngại bết rít cho da dầu",
          "narrative_text_vi": "Kết cấu lỏng nhẹ, thấm nhanh, không nhờn rít. 4–6 giọt thoa lên mặt sạch là đủ, dùng sáng tối đều được.",
          "compliance_note": "Sensory claim có thể demonstrate on camera. Nhắc tránh vùng mắt nếu có chỗ."
        },
        {
          "beat_id": "B6",
          "purpose": "credibility_softener",
          "duration_sec": 3,
          "new_reason_to_watch": "Disclaimer tự nhiên – tăng trust, không giảm mood video",
          "narrative_text_vi": "Kết quả tùy da mỗi người nha, nhưng combo thành phần này đáng để thử.",
          "compliance_note": "Disclaimer bắt buộc, diễn đạt casual. Không cam kết timeline kết quả."
        },
        {
          "beat_id": "B7",
          "purpose": "cta_soft",
          "duration_sec": 3,
          "new_reason_to_watch": "CTA soft – gợi hành động nhẹ nhàng, giữ cảm giác chia sẻ bạn bè",
          "narrative_text_vi": "Lưu lại nếu bạn cũng đang tìm serum cho da dầu nhé. Comment cho mình biết da bạn đang gặp vấn đề gì!",
          "compliance_note": "Soft CTA: save + comment. Không ép mua, không link sản phẩm, không urgency giả tạo."
        }
      ],
      "shots": [
        {
          "shot_id": "S1",
          "beat_id": "B1",
          "duration_sec": 4,
          "camera": "Close-up bàn tay cầm lọ serum nâu hổ phách, nâng lên ngang ngực – focus sắc nét vào lọ",
          "action": "Tay đưa lọ serum vào khung hình từ dưới lên, xoay nhẹ để thấy nhãn. Text overlay xuất hiện theo nhịp.",
          "visual_prompt_en": "Close-up of a hand holding an amber glass dropper bottle, lifting it into frame against a soft cream background. Warm ring light reflection on glass. Sharp focus on bottle label. 9:16 vertical, cinematic shallow depth of field.",
          "on_screen_text_vi": "7% NIACINAMIDE\n0.8% BHA\n4% NAG\n→ trong 1 lọ serum bí đao?",
          "voiceover_vi": "7% Niacinamide, 0.8% BHA, 4% NAG – combo này trong một lọ serum bí đao?",
          "sfx_music_hint": "Lo-fi beat nhẹ bắt đầu, có 1 sound effect 'pop' nhỏ khi text nồng độ xuất hiện",
          "product_visibility": "hero_moment",
          "claim_guardrail": "Chỉ nêu nồng độ – không gắn claim hiệu quả trong shot này",
          "fallback_if_generation_fails": "Static shot lọ serum trên bàn, text overlay animate từng dòng nồng độ"
        },
        {
          "shot_id": "S2",
          "beat_id": "B2",
          "duration_sec": 5,
          "camera": "Medium close-up mặt creator, framing từ ngực trở lên – ánh mắt nhìn thẳng lens",
          "action": "Creator nói chuyện trực tiếp với camera, biểu cảm tự nhiên kiểu 'mình hiểu cảm giác đó'. Lọ serum để trên bàn phía trước, visible nhưng không focus.",
          "visual_prompt_en": "Medium close-up of a young Vietnamese woman talking to camera, natural expression, hair clipped back, plain light top. Amber dropper bottle visible on desk in soft focus foreground. Warm natural window light, minimal makeup, 9:16.",
          "on_screen_text_vi": "Da dầu + mụn ẩn + thâm lì = quen không? 😅",
          "voiceover_vi": "Da dầu, mụn ẩn li ti, thâm mãi không mờ – quen không? Mình cũng vậy, nên mình tìm hiểu kỹ serum này.",
          "sfx_music_hint": "Lo-fi beat tiếp tục, giữ tempo nhẹ",
          "product_visibility": "subtle_background",
          "claim_guardrail": "Chỉ nêu pain point cá nhân, không claim sản phẩm giải quyết vấn đề",
          "fallback_if_generation_fails": "Text overlay pain point trên nền gradient pastel nhẹ + voiceover giữ nguyên"
        },
        {
          "shot_id": "S3",
          "beat_id": "B3",
          "duration_sec": 8,
          "camera": "Split: 4s close-up dropper nhỏ giọt serum lên mu bàn tay → 4s medium shot creator giải thích, tay cầm lọ",
          "action": "Phần 1: Dropper nhỏ 2–3 giọt serum lên tay – thấy rõ texture lỏng không màu. Phần 2: Creator giơ lọ nói về Niacinamide và BHA, text overlay highlight nồng độ.",
          "visual_prompt_en": "Part 1: Extreme close-up of glass dropper releasing clear liquid serum drops onto the back of a hand, soft bokeh background, warm light. Part 2: Young woman holding amber bottle near face level, talking naturally, text overlay appearing beside her. 9:16.",
          "on_screen_text_vi": "✦ 7% NIACINAMIDE → hỗ trợ cải thiện mụn, giảm lỗ chân lông\n✦ 0.8% BHA → thấm sâu, thông thoáng lỗ chân lông",
          "voiceover_vi": "7% Niacinamide hỗ trợ cải thiện mụn và giảm lỗ chân lông to. 0.8% BHA thấm vào lỗ chân lông, giúp thông thoáng – mà nồng độ này dịu nhẹ nha.",
          "sfx_music_hint": "Soft 'ding' khi mỗi dòng text xuất hiện. Lo-fi nền giữ nguyên.",
          "product_visibility": "balanced",
          "claim_guardrail": "Dùng đúng allowed_phrasing_vi cho Niacinamide và BHA. BHA dịu nhẹ – không nói 'an toàn tuyệt đối cho da nhạy cảm'.",
          "fallback_if_generation_fails": "Graphic card đơn giản: icon thành phần + text nồng độ + voiceover"
        },
        {
          "shot_id": "S4",
          "beat_id": "B4",
          "duration_sec": 6,
          "camera": "Close-up lọ serum xoay chậm trên bàn, rồi zoom out nhẹ thấy creator ngồi cạnh",
          "action": "Lọ serum quay chậm 360° (hoặc tay xoay nhẹ), text overlay NAG 4% xuất hiện. Creator vào khung hình giải thích ngắn.",
          "visual_prompt_en": "Close-up amber glass bottle slowly rotating on a clean surface, then slight zoom out revealing the young woman sitting beside it, gesturing casually as she explains. Warm lighting, 9:16.",
          "on_screen_text_vi": "✦ 4% NAG → cải thiện ẩm + sáng da\n⚡ Combo NAG + Niacinamide = đội hình mạnh",
          "voiceover_vi": "Còn NAG 4% – cái tên nghe lạ nhưng nó giúp cải thiện độ ẩm và hỗ trợ sáng da, nhất là khi đi chung với Niacinamide.",
          "sfx_music_hint": "Nhạc giữ tempo, thêm subtle bass drop nhỏ khi nói 'đội hình mạnh'",
          "product_visibility": "balanced",
          "claim_guardrail": "Dùng đúng allowed_phrasing cho NAG. 'Hỗ trợ sáng da' – không dùng 'trắng da' hay 'trị thâm'.",
          "fallback_if_generation_fails": "Text card NAG + giải thích ngắn trên nền pastel, voiceover giữ nguyên"
        },
        {
          "shot_id": "S5",
          "beat_id": "B5",
          "duration_sec": 6,
          "camera": "POV close-up: tay nhỏ serum lên lòng bàn tay → thoa lên mặt trước gương",
          "action": "4–6 giọt serum nhỏ vào lòng bàn tay, xoa nhẹ, rồi thoa lên má và trán. Camera bắt texture thấm nhanh trên da.",
          "visual_prompt_en": "First-person POV: hands dispensing 4-5 drops of clear serum into palm from dropper, then gently patting onto cheeks and forehead in front of a vanity mirror. Close-up showing lightweight texture absorbing quickly into skin. Warm ambient light, 9:16.",
          "on_screen_text_vi": "Lỏng nhẹ · thấm nhanh · không nhờn rít\n4–6 giọt, sáng tối đều được ✓",
          "voiceover_vi": "Kết cấu lỏng nhẹ, thấm nhanh, không nhờn rít. 4–6 giọt thoa lên mặt sạch là đủ, dùng sáng tối đều được.",
          "sfx_music_hint": "ASMR nhẹ: tiếng giọt serum rơi vào tay, tiếng thoa da. Lo-fi nền volume giảm nhẹ.",
          "product_visibility": "natural_use_first",
          "claim_guardrail": "Sensory claim demonstrable on camera. Không thêm claim hiệu quả ngoài texture.",
          "fallback_if_generation_fails": "Close-up chỉ bàn tay + dropper + text overlay sensory, không cần mặt"
        },
        {
          "shot_id": "S6",
          "beat_id": "B6",
          "duration_sec": 3,
          "camera": "Medium close-up creator, biểu cảm chân thành, nhìn lens",
          "action": "Creator nói disclaimer casual, gật nhẹ, nét mặt thân thiện – không nghiêm trọng.",
          "visual_prompt_en": "Medium close-up of the same young woman, sincere expression, slight nod, looking at camera. Same warm lighting and background as earlier shots. 9:16.",
          "on_screen_text_vi": "Kết quả tùy da mỗi người nha 🤍",
          "voiceover_vi": "Kết quả tùy da mỗi người nha, nhưng combo thành phần này đáng để thử.",
          "sfx_music_hint": "Lo-fi nền giữ nguyên, không thêm SFX",
          "product_visibility": "subtle_background",
          "claim_guardrail": "Disclaimer bắt buộc. Không cam kết timeline. 'Đáng để thử' – không phải 'sẽ có kết quả'.",
          "fallback_if_generation_fails": "Text overlay disclaimer trên nền video trước đó, giữ voiceover"
        },
        {
          "shot_id": "S7",
          "beat_id": "B7",
          "duration_sec": 3,
          "camera": "Medium shot creator cười nhẹ, vẫy tay hoặc chỉ xuống (cue comment). Lọ serum visible trên bàn.",
          "action": "Creator kết bằng CTA soft, biểu cảm vui vẻ thân thiện. Chỉ nhẹ xuống hoặc ra dấu 'lưu lại'. Lọ serum nằm tự nhiên trong khung hình.",
          "visual_prompt_en": "Medium shot of the young woman smiling, making a casual 'save' gesture or pointing down. Amber serum bottle visible on desk. Same warm setting. 9:16.",
          "on_screen_text_vi": "💾 Lưu lại nếu da dầu cũng cần serum\n💬 Comment da bạn đang gặp gì?",
          "voiceover_vi": "Lưu lại nếu bạn cũng đang tìm serum cho da dầu nhé. Comment cho mình biết da bạn đang gặp vấn đề gì!",
          "sfx_music_hint": "Lo-fi beat fade out nhẹ nhàng cuối video",
          "product_visibility": "subtle_background",
          "claim_guardrail": "Soft CTA only. Không ép mua, không link, không urgency.",
          "fallback_if_generation_fails": "End card text CTA + logo Cocoon nhỏ góc, voiceover giữ nguyên"
        }
      ],
      "caption_package": {
        "caption_vi": "Serum bí đao mà có 7% Niacinamide + 0.8% BHA + 4% NAG 🧪 Da dầu mụn ẩn nên biết combo này! Kết quả tùy da mỗi người nha 🤍 Lưu lại tham khảo!",
        "pinned_comment_vi": "Bạn nào da dầu đang bị mụn ẩn hay thâm lì – comment cho mình biết, mình chia sẻ thêm nha 💬",
        "hashtags_vi": [
          "#serumbidao",
          "#cocoonvietnam",
          "#niacinamide",
          "#skincareVN",
          "#dadaumunẩn",
          "#thanhphanmypham",
          "#skincareedu",
          "#serumchadadau",
          "#BHA",
          "#tiktokvietnam"
        ]
      },
      "quality_checks": {
        "objective_fit_checklist": [
          "✓ Primary intent education_soft_product_support: video giải thích 3 thành phần chính với nồng độ cụ thể",
          "✓ Secondary intent entertainment_relatable: beat B2 kết nối pain point cá nhân, tone casual xuyên suốt",
          "✓ Viewer outcome learn: mỗi beat ingredient giải thích 1 thành phần mới",
          "✓ Viewer outcome save: nội dung mang tính tham khảo thành phần lâu dài",
          "✓ Viewer outcome comment: CTA gợi chia sẻ vấn đề da cá nhân",
          "✓ Hook 0–3s chạm pain point + tạo curiosity bằng 3 con số nồng độ"
        ],
        "compliance_checklist": [
          "✓ Không dùng từ điều trị/chữa bệnh da liễu",
          "✓ Không cam kết timeline kết quả",
          "✓ Không so sánh với sản phẩm/thương hiệu khác",
          "✓ Không tuyên bố collagen có kiểm chứng lâm sàng",
          "✓ Không tuyên bố chống nắng/bảo vệ UV",
          "✓ Không hard CTA – chỉ soft save/comment",
          "✓ Không before/after transformation claim",
          "✓ Không nâng 'hỗ trợ cải thiện' thành 'điều trị/chữa lành'",
          "✓ Chỉ dùng nồng độ đã xác nhận: 7% Niacinamide, 0.8% BHA, 4% NAG",
          "✓ Disclaimer kết quả tùy cơ địa xuất hiện tự nhiên trong B6",
          "✓ Không dùng bất kỳ forbidden_phrasing_vi nào"
        ],
        "continuity_checklist": [
          "✓ Cùng 1 creator xuyên suốt 7 shot – không đổi người",
          "✓ Wardrobe giữ nguyên: áo trơn nhạt",
          "✓ Environment giữ nguyên: góc bàn skincare tối giản",
          "✓ Lighting nhất quán: warm ring light / ánh sáng cửa sổ",
          "✓ Lọ serum nâu hổ phách xuất hiện liên tục với mức visibility phù hợp từng beat",
          "✓ Camera behavior: close-up và medium close-up, không wide shot, không transition phức tạp",
          "✓ Tone voiceover nhất quán: casual, thân thiện, không chuyển sang giọng chuyên gia giữa video"
        ]
      }
    }
  }