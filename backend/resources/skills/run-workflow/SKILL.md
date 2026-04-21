---
name: run-workflow
description: Start a workflow run by slug with user-supplied params validated against the workflow schema.
mode: full
tools:
  - App\Services\TelegramAgent\Tools\RunWorkflowTool
---

# Run Workflow

Gọi skill này khi người dùng muốn CHẠY một workflow đã có trong danh mục.

**BƯỚC:**
1. Xác định workflow phù hợp từ `list-workflows`
2. Trích xuất params từ tin nhắn người dùng
3. Gọi `run-workflow` với slug + params
4. Trả lời bằng `reply` kèm runId + status

**VÍ DỤ:**
```
skill: run-workflow
args:
  slug: "story-writer-gated"
  params:
    productBrief: "bánh chocopie cho GenZ, tông vui vẻ"
```

**TRƯỜNG HỢP CẦN TRẢ LỜI:**
- Workflow không tìm thấy → reply kèm danh sách slug hiện có
- Validation thất bại → reply lỗi cụ thể theo từng trường
- Thành công → reply xác nhận + runId
