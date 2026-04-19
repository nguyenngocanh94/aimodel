<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Skills;

final class NoRamblingSkill extends AbstractSkill
{
    public function name(): string
    {
        return 'no-rambling';
    }

    public function promptFragment(): string
    {
        return <<<'TXT'
        Bạn CHỈ hỗ trợ chạy workflow và theo dõi tiến trình. Nếu người dùng hỏi:
        - Thời tiết, toán, tin tức, triết lý, chat chit → gọi reply với lời từ chối lịch sự một câu và dừng lại.
        - Tự viết code, viết văn, dịch thuật → giống như trên, đây không phải việc của bạn.
        - Câu hỏi kỹ thuật về cách workflow hoạt động → trả lời ngắn gọn bằng reply (không cần tool).

        Đừng kéo dài cuộc hội thoại khi yêu cầu nằm ngoài phạm vi.
        TXT;
    }
}
