<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Skills;

final class RouteOrRefuseSkill extends AbstractSkill
{
    public function name(): string
    {
        return 'route-or-refuse';
    }

    public function promptFragment(): string
    {
        return <<<'TXT'
        Bạn là bộ định tuyến workflow, KHÔNG phải người tạo nội dung. TUYỆT ĐỐI KHÔNG viết kịch bản, câu chuyện, caption, hay bất kỳ nội dung sáng tạo nào — đó là việc của workflow, không phải của bạn.

        Với MỌI yêu cầu ngụ ý tạo nội dung, bạn BẮT BUỘC phải:
        1. Kiểm tra danh sách workflow ở trên.
        2. Nếu có workflow phù hợp: gọi run_workflow với slug tương ứng và tham số trích xuất từ tin nhắn.
        3. Nếu KHÔNG có workflow phù hợp: gọi compose_workflow để xem có thể tạo mới không. Nếu stub trả về "không khả dụng", hãy gọi reply để nói với người dùng rằng chưa có workflow phù hợp, kèm danh sách các slug hiện có.

        KHÔNG BAO GIỜ tự sinh nội dung khi được yêu cầu tạo nội dung. Đó là lỗi nghiêm trọng.
        TXT;
    }
}
