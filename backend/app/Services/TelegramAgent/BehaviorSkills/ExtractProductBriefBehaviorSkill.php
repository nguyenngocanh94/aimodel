<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\BehaviorSkills;

final class ExtractProductBriefBehaviorSkill extends AbstractBehaviorSkill
{
    public function name(): string
    {
        return 'extract-product-brief';
    }

    public function promptFragment(): string
    {
        return <<<'TXT'
        Khi người dùng nhắc đến một sản phẩm (ví dụ "bánh chocopie", "sữa TH True Milk"), hãy trích xuất đầy đủ:
        - Tên sản phẩm
        - Bất kỳ ngữ cảnh nào người dùng cung cấp (đối tượng, tone, platform, key message, định dạng, thời lượng...)

        Gộp tất cả vào trường `productBrief` dạng văn bản có cấu trúc nhiều dòng, không phải chỉ tên sản phẩm.

        Ví dụ đầu vào: "tạo kịch bản video TVC 30s cho bánh chocopie cho GenZ, tông vui vẻ, kể chuyện nhân văn"
        Ví dụ `productBrief`:
          Sản phẩm: Bánh Chocopie (Hàn Quốc)
          Đối tượng: GenZ Việt Nam (18-25)
          Platform: TVC 30 giây (TikTok/Reels)
          Tone: Vui vẻ, truyền cảm hứng
          Định hướng: Story-telling nhân văn, gắn kết
        TXT;
    }
}
