<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessTelegramBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(
        private string $sessionKey,
        private string $botToken,
        private string $chatId,
    ) {}

    public function handle(): void
    {
        // Check if this batch was cancelled (a newer message reset the timer)
        $currentBatchId = Redis::get("telegram_batch_id:{$this->sessionKey}");
        $jobKey = "telegram_batch_job:{$this->chatId}:{$this->botToken}";
        $expectedBatchId = Redis::get($jobKey);

        // If batch IDs don't match, this is a stale job — skip
        if ($currentBatchId && $expectedBatchId && $currentBatchId !== $expectedBatchId) {
            Log::info('Skipping stale Telegram batch job', [
                'chatId' => $this->chatId,
                'currentBatchId' => $currentBatchId,
                'expectedBatchId' => $expectedBatchId,
            ]);
            return;
        }

        $session = $this->getSession();

        if ($session === null || ($session['status'] ?? '') !== 'buffering') {
            return;
        }

        // Build summary of what was received
        $texts = $session['texts'] ?? [];
        $images = $session['images'] ?? [];
        $messageCount = count($session['messages'] ?? []);

        $summary = "📋 *Đã nhận thông tin:*\n\n";

        if (!empty($texts)) {
            $combinedText = implode("\n", $texts);
            $summary .= "📝 *Nội dung:*\n" . mb_substr($combinedText, 0, 1000) . "\n\n";
        }

        if (!empty($images)) {
            $summary .= "🖼 *Hình ảnh:* " . count($images) . " ảnh\n\n";
        }

        // Extract image URLs from text
        $allText = implode("\n", $texts);
        $urlCount = 0;
        if (preg_match_all('/https?:\/\/[^\s<>"]+\.(?:jpg|jpeg|png|webp|gif)/i', $allText, $urlMatches)) {
            $urlCount = count($urlMatches[0]);
            if ($urlCount > 0 && empty($images)) {
                $summary .= "🔗 *URL hình ảnh:* {$urlCount} link\n\n";
            }
        }

        $summary .= "📊 *Tổng:* {$messageCount} tin nhắn, " . (count($images) + $urlCount) . " hình ảnh\n\n";
        $summary .= "Reply *ok* để xác nhận và bắt đầu tạo TVC, hoặc *hủy* để bỏ.";

        // Update session status
        $session['status'] = 'awaiting_confirmation';
        $this->saveSession($session);

        // Send summary to Telegram
        try {
            Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => mb_substr($summary, 0, 4096),
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send Telegram batch summary', [
                'chatId' => $this->chatId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Telegram batch ready for confirmation', [
            'chatId' => $this->chatId,
            'texts' => count($texts),
            'images' => count($images),
        ]);
    }

    private function getSession(): ?array
    {
        $data = Redis::get($this->sessionKey);
        return $data ? json_decode($data, true) : null;
    }

    private function saveSession(array $session): void
    {
        Redis::set($this->sessionKey, json_encode($session, JSON_THROW_ON_ERROR), 'EX', 120);
    }
}
