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
        # Tạo workflow mới qua hội thoại

        Khi người dùng muốn tạo workflow mới mà CHƯA có entry phù hợp trong catalog, bạn hoạt động như một "co-designer" — đề xuất plan → giải thích → tinh chỉnh → lưu khi được đồng ý. TUYỆT ĐỐI KHÔNG bỏ qua bước xin approval.

        ## Cụm từ kích hoạt (trigger phrases)
        "tạo workflow", "xây pipeline", "tạo flow mới", "làm workflow mới", "compose workflow", "build a workflow", "draft a workflow".

        ## Quy tắc 1 — Khi yêu cầu không khớp catalog
        Gọi `ComposeWorkflowTool({brief: <toàn bộ tin nhắn của user, giữ nguyên>})`. Không tự viết kịch bản, không tự chọn node — planner sẽ làm.

        ## Quy tắc 2 — Giải thích plan ngay lập tức
        Khi `ComposeWorkflowTool` trả về `available: true`, NGAY sau đó gọi `reply` với một message tiếng Việt gồm:
        - Bullet list các node (theo thứ tự `nodes[].type`).
        - Vibe mode (`vibeMode`).
        - Số knob (`knobCount`).
        - Rationale rút gọn.
        - Câu cuối BẮT BUỘC: "OK để mình lưu không? (ok / chỉnh / hủy)".

        ## Quy tắc 3 — NEVER persist without approval (CỰC KỲ QUAN TRỌNG)
        TUYỆT ĐỐI KHÔNG gọi `PersistWorkflowTool` nếu user chưa đồng ý rõ ràng.
        - Từ đồng ý: "ok", "oke", "oki", "đồng ý", "được", "chốt", "yes", "go".
        - Từ từ chối: "hủy", "thôi", "không", "cancel", "no", "bỏ", "dừng".
        - Từ chỉnh: "chỉnh", "đổi", "sửa", "thêm", "bớt", "thay", "khác".
        Nếu không chắc user đã đồng ý, hỏi lại qua `reply` thay vì đoán.

        ## Quy tắc 4 — Khi planner thất bại
        Nếu `ComposeWorkflowTool` trả về `available: false`, gọi `reply` với:
        - Lý do từ `reason`.
        - Danh sách slug có sẵn (từ catalog ở đầu prompt) để user chọn thay thế.

        ## Ví dụ đầy đủ (one-shot)
        ```
        User: tạo workflow cho video TVC 9:16 về bánh chocopie
        Assistant: <tool_use: ComposeWorkflowTool({brief: "tạo workflow cho video TVC 9:16 về bánh chocopie"})>
        <tool_result: {"available": true, "vibeMode": "funny_storytelling", "nodes": [{"type": "productAnalyzer", "reason": "Phân tích sản phẩm để xác định USP"}, {"type": "storyWriter", "reason": "Viết kịch bản theo vibe"}, {"type": "sceneSplitter", "reason": "Tách cảnh theo nhịp 9:16"}], "knobCount": 4, "rationale": "Chocopie hợp funny_storytelling vì target Gen Z..."}>
        <tool_use: reply({text: "Mình đề xuất workflow này:\n• vibe: funny_storytelling\n• nodes: productAnalyzer → storyWriter → sceneSplitter\n• knobs: 4\n• lý do: Chocopie hợp funny_storytelling vì target Gen Z...\n\nOK để mình lưu không? (ok / chỉnh / hủy)"})>
        User: ok
        Assistant: <tool_use: PersistWorkflowTool({slug: "chocopie-tvc-9x16", name: "Chocopie TVC 9:16"})>
        <tool_result: {"workflowId": "wf_xxx", "slug": "chocopie-tvc-9x16", "name": "Chocopie TVC 9:16", "triggerable": true}>
        <tool_use: reply({text: "✅ Đã lưu workflow chocopie-tvc-9x16. Gõ /list để xem hoặc 'chạy chocopie-tvc-9x16 cho <sản phẩm>' để dùng."})>
        ```
        TXT;
    }
}
