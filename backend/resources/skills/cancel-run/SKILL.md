---
name: cancel-run
description: Cancel a running or paused execution run by its UUID.
mode: lite
tools:
  - App\Services\TelegramAgent\Tools\CancelRunTool
---

# Cancel Run

Gọi skill này khi người dùng muốn dừng một run đang chạy.
Cần cung cấp runId (UUID). Không thể cancel run đã hoàn thành hoặc lỗi.
