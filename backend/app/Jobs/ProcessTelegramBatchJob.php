<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TelegramAgent\TelegramAgentFactory;
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

    /**
     * Compose/plan turns can trigger multiple LLM round-trips in one queued job:
     * agent routing + planner/refiner tool call(s). 120s can be exhausted by
     * two 60s upstream calls plus middleware overhead, causing worker timeout.
     */
    public int $timeout = 300;

    public function __construct(
        private string $sessionKey,
        private string $botToken,
        private string $chatId,
        private string $batchId = '',
    ) {}

    public function handle(): void
    {
        // #region agent log
        $runId = 'initial';
        $this->debugLog($runId, 'H1', 'ProcessTelegramBatchJob.php:34', 'job_handle_enter', [
            'sessionKey' => $this->sessionKey,
            'chatId' => $this->chatId,
            'hasBatchId' => $this->batchId !== '',
        ]);
        // #endregion
        try {
            // Stale-batch detection: this job was dispatched with its own immutable
            // $batchId at enqueue time. bufferMessage() updates the "latest batch"
            // pointer in Redis every time a new message arrives. If the current
            // latest is NOT our batchId, a newer burst superseded us — skip.
            //
            // batchId is '' only for legacy/test dispatches that predate FX-05;
            // fall back to the old (effectively no-op) comparison in that case so
            // nothing crashes, but real production dispatches always set batchId.
            if ($this->batchId !== '') {
                $latestBatchId = Redis::get("telegram_batch_job:{$this->chatId}:{$this->botToken}");
                // #region agent log
                $this->debugLog($runId, 'H2', 'ProcessTelegramBatchJob.php:53', 'latest_batch_lookup', [
                    'latestBatchId' => is_string($latestBatchId) ? $latestBatchId : (string) $latestBatchId,
                    'myBatchId' => $this->batchId,
                ]);
                // #endregion
                if ($latestBatchId !== null && $latestBatchId !== false && $latestBatchId !== $this->batchId) {
                    Log::info('Skipping stale Telegram batch job', [
                        'chatId'         => $this->chatId,
                        'myBatchId'      => $this->batchId,
                        'latestBatchId'  => $latestBatchId,
                    ]);
                    return;
                }
            }

            $session = $this->getSession();
            // #region agent log
            $this->debugLog($runId, 'H3', 'ProcessTelegramBatchJob.php:69', 'session_loaded', [
                'sessionExists' => $session !== null,
                'status' => is_array($session) ? ($session['status'] ?? null) : null,
                'messageCount' => is_array($session) ? count($session['messages'] ?? []) : 0,
            ]);
            // #endregion

            if ($session === null || ($session['status'] ?? '') !== 'buffering') {
                return;
            }

            // Flatten buffered burst into a single combined update.
            $combined = $this->assembleCombinedUpdate($session);
            // #region agent log
            $this->debugLog($runId, 'H4', 'ProcessTelegramBatchJob.php:83', 'combined_update_assembled', [
                'textLength' => strlen((string) ($combined['message']['text'] ?? '')),
                'hasPhoto' => isset($combined['message']['photo']),
            ]);
            // #endregion

            // Clear the intake session immediately so the key doesn't linger.
            $this->clearSession();

            // Call the agent. The finally block sends a fallback reply if the agent
            // throws before any ReplyTool call has sent something to the user.
            $replySent = false;

            try {
                /** @var TelegramAgentFactory $factory */
                $factory = app(TelegramAgentFactory::class);
                $agent   = $factory->make($this->chatId, $this->botToken);
                $agent->handle($combined, $this->botToken);
                $replySent = true;
            } catch (\Throwable $e) {
                Log::error('TelegramAgent threw during batch job', [
                    'chatId' => $this->chatId,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString(),
                ]);
                // #region agent log
                $this->debugLog($runId, 'H5', 'ProcessTelegramBatchJob.php:108', 'agent_handle_throwable', [
                    'error' => $e->getMessage(),
                    'class' => $e::class,
                ]);
                // #endregion
            } finally {
                if (! $replySent) {
                    $this->sendFallbackReply();
                }
            }

            Log::info('Telegram batch job finished', [
                'chatId'   => $this->chatId,
                'messages' => count($session['messages'] ?? []),
            ]);
        } catch (\Throwable $e) {
            // #region agent log
            $this->debugLog($runId, 'H1', 'ProcessTelegramBatchJob.php:125', 'job_handle_uncaught_throwable', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            // #endregion
            throw $e;
        }
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

    private function clearSession(): void
    {
        Redis::del($this->sessionKey);
    }

    /**
     * Send a generic apology when the agent turn throws before any ReplyTool
     * call has reached the user. Side effects (DB writes, dispatched jobs) may
     * have already committed — DO NOT retry the agent turn here.
     */
    private function sendFallbackReply(): void
    {
        try {
            Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id'    => $this->chatId,
                'text'       => '⚠️ Xảy ra lỗi trong quá trình xử lý. Vui lòng thử lại sau.',
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProcessTelegramBatchJob: failed to send fallback reply', [
                'chatId' => $this->chatId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        // Handle worker-level timeout/fatal paths where handle() finally blocks
        // may not run, so the user would otherwise see no Telegram response.
        $this->sendFallbackReply();
        // #region agent log
        $this->debugLog('post-fix', 'H12', 'ProcessTelegramBatchJob.php:211', 'job_failed_handler_invoked', [
            'chatId' => $this->chatId,
            'error' => $e->getMessage(),
            'class' => $e::class,
        ]);
        // #endregion
    }

    /**
     * @param array<string, mixed> $data
     */
    private function debugLog(string $runId, string $hypothesisId, string $location, string $message, array $data = []): void
    {
        try {
            file_put_contents('/Volumes/Work/Workspace/AiModel/.cursor/debug-477860.log', json_encode([
                'sessionId' => '477860',
                'runId' => $runId,
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // no-op: debug logging must never affect job processing
        }
    }
}
