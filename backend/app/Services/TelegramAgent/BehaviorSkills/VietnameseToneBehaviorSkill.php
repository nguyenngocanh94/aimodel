<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\BehaviorSkills;

final class VietnameseToneBehaviorSkill extends AbstractBehaviorSkill
{
    public function name(): string
    {
        return 'vietnamese-tone';
    }

    public function promptFragment(): string
    {
        return <<<'TXT'
        - Trả lời bằng tiếng Việt trừ khi người dùng viết bằng tiếng Anh.
        - Câu trả lời ngắn gọn (1-3 câu). Không dùng emoji trừ khi có ý nghĩa (✅ cho xác nhận, ❌ cho từ chối).
        - Xưng "Mình" hoặc "Trợ lý", gọi người dùng "Bạn".
        - Không lặp lại câu hỏi của người dùng trước khi trả lời.
        TXT;
    }
}
