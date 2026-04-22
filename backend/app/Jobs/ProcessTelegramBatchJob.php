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

    /**
     * Flatten buffered session messages into a single synthetic Telegram update
     * that can be passed directly to TelegramAgent::handle().
     *
     * - Texts are joined with double-newline (paragraph break).
     * - Photos are merged preserving insertion order.
     * - Burst metadata is preserved in message._intake for provenance.
     *
     * @param  array<string, mixed>  $session  The Redis-backed intake session.
     * @return array<string, mixed>  A Telegram-update-shaped array.
     */
    public function assembleCombinedUpdate(array $session): array
    {
        $texts  = $session['texts'] ?? [];
        $images = $session['images'] ?? [];
        $msgs   = $session['messages'] ?? [];

        // Joined text across all burst messages.
        $combinedText = implode("\n\n", array_filter(array_map('strval', $texts)));

        // Merge photo arrays in order. Each image stored as ['file_id' => ..., ...].
        $mergedPhotos = [];
        foreach ($images as $img) {
            $fileId = $img['file_id'] ?? null;
            if ($fileId !== null) {
                $mergedPhotos[] = $img;
            }
        }

        // Preserve first message metadata for chat/from/etc.
        $firstMsg = $msgs[0] ?? [];

        $message = [
            'chat'       => $firstMsg['chat'] ?? ['id' => (int) $this->chatId],
            'from'       => $firstMsg['from'] ?? [],
            'date'       => $firstMsg['date'] ?? time(),
            'message_id' => $firstMsg['message_id'] ?? 0,
            'text'       => $combinedText,
        ];

        // Only attach photo key when images were collected so image-only updates
        // pass the TelegramAgent early-exit guard.
        if ($mergedPhotos !== []) {
            $message['photo'] = $mergedPhotos;
        }

        // Burst provenance metadata (non-Telegram, agent-facing only).
        $message['_intake'] = [
            'textParts'    => $texts,
            'imageCount'   => count($mergedPhotos),
            'messageCount' => count($msgs),
            'startedAt'    => $session['startedAt'] ?? null,
        ];

        return [
            'update_id' => 0,
            'message'   => $message,
        ];
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
