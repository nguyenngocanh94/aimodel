<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Skills;

/**
 * Teaches the model the propose → explain → approve → persist loop for
 * conversational workflow composition.
 *
 * Order matters: this skill slots AFTER {@see RouteOrRefuseSkill} so the model
 * first tries to match the catalog; only then does it fall through to drafting.
 */
final class ComposeWorkflowSkill extends AbstractSkill
{
    public function name(): string
    {
        return 'compose-workflow';
    }

    public function promptFragment(): string
    {
        return <<<'TXT'
        **Tạo workflow mới qua chat.**

        Khi người dùng muốn tạo một workflow mới (không phải chạy workflow có sẵn), bạn BẮT BUỘC phải đi qua 3 bước:

        1. DRAFT — gọi `ComposeWorkflowTool({brief: "<tin nhắn gốc của user>"})` để planner tạo plan.
        2. EXPLAIN — ngay sau khi tool trả về `available: true`, gọi `reply` với:
           • bullet list các nodes (type + lý do ngắn)
           • vibe mode
           • vài knobs nổi bật
           • câu chốt: "OK để mình lưu không? (ok / chỉnh / hủy)"
        3. Đợi phản hồi của user → chọn 1 trong 3 đường:

        **APPROVAL** (user nói: ok, oki, oke, đồng ý, được, chốt, tiếp, làm đi, go, yes, ✅)
          → gọi `PersistWorkflowTool({slug: <kebab-case>, name: <Human Readable>})`
          → sau khi tool trả về `workflowId`, gọi `reply` xác nhận đã lưu + hướng dẫn dùng.

        **REFINEMENT** (user nói: chỉnh, đổi, thay, khác, sửa, thêm, bớt, lại, retry, update, hoặc mô tả cụ thể)
          → gọi `RefinePlanTool({feedback: "<lời user nói verbatim>"})`
          → sau khi tool trả về plan mới, gọi `reply` mô tả thay đổi + hỏi lại "OK chưa?".
          → Giới hạn 5 lần chỉnh. Khi `remaining: 0`, ép user chọn "ok" hoặc "hủy".

        **REJECTION** (user nói: hủy, thôi, không, dừng, bỏ, cancel, no)
          → gọi `reply` xác nhận đã hủy (không lưu), session tự expire.

        **QUY TẮC BẤT DI BẤT DỊCH:**
        • KHÔNG BAO GIỜ gọi `PersistWorkflowTool` khi user chưa rõ ràng đồng ý.
        • KHÔNG BAO GIỜ tự sinh workflow JSON — luôn qua tool.
        • Nếu `ComposeWorkflowTool` trả về `available: false`, gọi `reply` giải thích lý do + liệt kê các slug có sẵn từ catalog.
        • Nếu không chắc user đã đồng ý, hỏi lại qua `reply` trước khi chọn đường.

        **SLUG — kebab-case từ chủ đề brief:**
        • "sinh video TVC 9:16 chăm sóc sức khỏe" → slug: `health-tvc-9x16`
        • "video ngắn cho trà sữa" → slug: `milktea-short-video`
        • "tạo workflow pitch deck B2B" → slug: `b2b-pitch-deck`
        • "video unbox iPhone 15" → slug: `iphone-15-unbox`
        Tên ngắn, không dấu, kebab. Không bao gồm từ "workflow" / "video" nếu thừa.

        **VÍ DỤ ĐẦY ĐỦ (one-shot):**
        ```
        User: tạo workflow sinh video TVC 9:16 chăm sóc sức khỏe
        Assistant: <tool_use: ComposeWorkflowTool({brief: "tạo workflow sinh video TVC 9:16 chăm sóc sức khỏe"})>
        <tool_result: {"available": true, "vibeMode": "clean_education", "nodes": [{"type": "productAnalyzer", "reason": "Xác định USP sản phẩm"}, {"type": "storyWriter", "reason": "Kịch bản đáng tin cậy"}, {"type": "sceneSplitter", "reason": "Tách cảnh 9:16"}, {"type": "promptRefiner", "reason": "Tinh chỉnh prompt ảnh"}, {"type": "videoComposer", "reason": "Ghép video cuối"}], "knobCount": 5, "rationale": "Sức khỏe cần tin cậy, hero_moment tăng tín nhiệm..."}>
        <tool_use: reply({text: "Mình đề xuất workflow này:\n• vibe: clean_education\n• nodes: productAnalyzer → storyWriter → sceneSplitter → promptRefiner → videoComposer\n• knobs: humor_density=none, product_emphasis=hero_moment, cta_softness=medium\n• lý do: Sức khỏe cần tin cậy, hero_moment tăng tín nhiệm...\n\nOK để mình lưu không? (ok / chỉnh / hủy)"})>
        User: chỉnh: thêm humor nhẹ, đừng khô khan
        Assistant: <tool_use: RefinePlanTool({feedback: "thêm humor nhẹ, đừng khô khan"})>
        <tool_result: {"available": true, "attempt": 2, "remaining": 3, "vibeMode": "clean_education", "nodes": [...], "rationale": "Cập nhật humor_density=punchline_only..."}>
        <tool_use: reply({text: "Cập nhật: humor_density=punchline_only, emotional_tone=relatable_humor. Phần còn lại giữ nguyên.\n\nOK chưa? (ok / chỉnh / hủy)"})>
        User: ok
        Assistant: <tool_use: PersistWorkflowTool({slug: "health-tvc-9x16", name: "Health TVC 9:16"})>
        <tool_result: {"workflowId": "wf_abc123", "slug": "health-tvc-9x16", "name": "Health TVC 9:16", "triggerable": true, "message": "Đã lưu workflow health-tvc-9x16..."}>
        <tool_use: reply({text: "✅ Đã lưu workflow health-tvc-9x16. Gõ /list để xem hoặc 'chạy health-tvc-9x16 cho <sản phẩm>' để dùng."})>
        User: hủy (ví dụ nếu user muốn hủy thay vì ok ở bước trên)
        Assistant: <tool_use: reply({text: "Đã hủy. Workflow chưa được lưu. Nhắn lại bất cứ lúc nào để tạo mới."})>
        ```
        TXT;
    }
}
