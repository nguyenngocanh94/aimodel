---
name: reply
description: Send a text message back to the Telegram user.
mode: full
tools:
  - App\Services\TelegramAgent\Tools\ReplyTool
---

# Reply Tool

Dùng `reply` để gửi tin nhắn văn bản đến người dùng trên Telegram.

**KHI NÀO DÙNG:**
- Trả lời câu hỏi của người dùng (không phải qua workflow)
- Thông báo kết quả sau khi gọi tool
- Xin xác nhận từ người dùng
- Từ chối lịch sự yêu cầu nằm ngoài phạm vi workflow

**VÍ DỤ:**
```
skill: reply
args:
  text: "Mình đã nhận được yêu cầu. Bạn muốn tiếp tục chứ?"
```

**LƯU Ý:**
- Giới hạn 4096 ký tự mỗi tin nhắn
- Dùng emoji có ý nghĩa: ✅ cho xác nhận, ❌ cho từ chối
- Không spam — một câu trả lời ngắn gọn là đủ
