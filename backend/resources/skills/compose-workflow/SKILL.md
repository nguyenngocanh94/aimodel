---
name: compose-workflow
description: Draft a new workflow from a user brief. Returns a plan without persisting. Requires user approval before persist.
mode: full
tools:
  - App\Services\TelegramAgent\Tools\ComposeWorkflowTool
---

# Compose Workflow

Dùng skill này khi người dùng muốn TẠO MỘT WORKFLOW MỚI (chưa có trong danh mục).

**QUY TRÌNH 3 BƯỚC:**

**1. DRAFT** — Gọi `compose-workflow` với brief đầy đủ:
```
skill: compose-workflow
args:
  brief: "tạo workflow sinh video TVC 9:16 chăm sóc sức khỏe"
```

**2. EXPLAIN** — Sau khi tool trả về `available: true`:
→ Dùng `reply` gửi:
- bullet list các nodes (type + lý do ngắn)
- vibe mode
- vài knobs nổi bật
- câu hỏi: "OK để mình lưu không? (ok / chỉnh / hủy)"

**3. ĐỢI PHẢN HỒI:**
- **OK / đồng ý** → gọi `persist-workflow`
- **chỉnh / đổi** → gọi `refine-workflow`
- **hủy / thôi** → `reply` xác nhận hủy

**QUY TẮC BẤT DI BẤT DỊCH:**
- KHÔNG BAO GIỜ gọi `persist-workflow` khi chưa có approval rõ ràng
- KHÔNG BAO GIỜ tự sinh workflow JSON — luôn qua tool
- Nếu `available: false` → reply lý do + slug hiện có
