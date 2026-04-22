---
name: get-run-status
description: Fetch current status, current node, and any pending interaction for a run id.
mode: lite
tools:
  - App\Services\TelegramAgent\Tools\GetRunStatusTool
---

# Get Run Status

Gọi skill này khi người dùng hỏi về tiến trình của một run đang chạy.
Cần cung cấp runId (UUID) mà người dùng đã nhận được từ lệnh `run-workflow`.
