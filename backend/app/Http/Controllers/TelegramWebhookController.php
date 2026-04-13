<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramBatchJob;
use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TelegramWebhookController extends Controller
{
    private const BUFFER_TTL = 120; // seconds to keep buffer alive
    private const DEBOUNCE_DELAY = 30; // seconds to wait for more messages

    public function handle(Request $request, string $botToken): JsonResponse
    {
        $update = $this->parseUpdate($request);

        if (empty($update)) {
            return response()->json(['ok' => true]);
        }

        // Priority 0: Handle inline keyboard callback (Approve/Reject buttons)
        if (!empty($update['callback_query'])) {
            $this->handleCallbackQuery($botToken, $update['callback_query']);
            return response()->json(['ok' => true]);
        }

        $chatId = (string) ($update['message']['chat']['id'] ?? '');
        $text = $update['message']['text'] ?? $update['message']['caption'] ?? '';

        if (empty($chatId)) {
            return response()->json(['ok' => true]);
        }

        // Priority 1: Check if this is a text response to a suspended HumanGate
        if ($this->tryResumeGate($botToken, $chatId, $text, $update)) {
            return response()->json(['ok' => true]);
        }

        // Priority 2: Check if this is a confirmation for a pending intake session
        $sessionKey = "telegram_intake:{$chatId}:{$botToken}";
        $session = $this->getSession($sessionKey);

        if ($session && ($session['status'] ?? '') === 'awaiting_confirmation') {
            $this->handleConfirmation($botToken, $chatId, $text, $session, $sessionKey);
            return response()->json(['ok' => true]);
        }

        // Priority 3: Buffer this message in an intake session
        $this->bufferMessage($botToken, $chatId, $update, $sessionKey);

        return response()->json(['ok' => true]);
    }

    /**
     * Buffer an incoming message and schedule/reset the debounce timer.
     */
    private function bufferMessage(string $botToken, string $chatId, array $update, string $sessionKey): void
    {
        $session = $this->getSession($sessionKey) ?? [
            'status' => 'buffering',
            'botToken' => $botToken,
            'chatId' => $chatId,
            'messages' => [],
            'images' => [],
            'texts' => [],
            'startedAt' => now()->toIso8601String(),
        ];

        $message = $update['message'] ?? [];

        // Extract text
        $text = $message['text'] ?? $message['caption'] ?? '';
        if ($text !== '') {
            $session['texts'][] = $text;
        }

        // Extract photos (Telegram sends multiple sizes, pick largest)
        if (!empty($message['photo'])) {
            $photos = $message['photo'];
            $largest = end($photos); // last = largest resolution
            $session['images'][] = [
                'file_id' => $largest['file_id'],
                'width' => $largest['width'] ?? 0,
                'height' => $largest['height'] ?? 0,
            ];
        }

        // Extract document/file if it's an image
        if (!empty($message['document'])) {
            $doc = $message['document'];
            $mime = $doc['mime_type'] ?? '';
            if (str_starts_with($mime, 'image/')) {
                $session['images'][] = [
                    'file_id' => $doc['file_id'],
                    'file_name' => $doc['file_name'] ?? '',
                    'mime_type' => $mime,
                ];
            }
        }

        // Store raw messages for reference
        $session['messages'][] = $message;
        $session['status'] = 'buffering';

        $this->saveSession($sessionKey, $session);

        // Schedule (or reschedule) the batch processing job
        $jobKey = "telegram_batch_job:{$chatId}:{$botToken}";
        $existingJobId = Redis::get($jobKey);

        // Cancel existing delayed job by marking it stale
        if ($existingJobId) {
            Redis::set("telegram_batch_stale:{$existingJobId}", '1', 'EX', self::BUFFER_TTL);
        }

        // Dispatch new delayed job
        $job = ProcessTelegramBatchJob::dispatch($sessionKey, $botToken, $chatId)
            ->delay(now()->addSeconds(self::DEBOUNCE_DELAY));

        // Store job ID so we can cancel it on next message
        // Use a unique ID since Laravel doesn't expose job IDs easily
        $batchId = uniqid('batch_', true);
        Redis::set($jobKey, $batchId, 'EX', self::BUFFER_TTL);
        Redis::set("telegram_batch_id:{$sessionKey}", $batchId, 'EX', self::BUFFER_TTL);

        Log::info('Telegram message buffered', [
            'chatId' => $chatId,
            'textCount' => count($session['texts']),
            'imageCount' => count($session['images']),
            'batchId' => $batchId,
        ]);
    }

    /**
     * Handle user's confirmation or rejection of the intake summary.
     */
    private function handleConfirmation(string $botToken, string $chatId, string $text, array $session, string $sessionKey): void
    {
        $normalizedText = mb_strtolower(trim($text));

        // Simple confirmation detection (LLM can be added later for nuance)
        $confirmWords = ['ok', 'yes', 'confirmed', 'confirm', 'go', 'proceed', 'oke', 'oki',
            'đồng ý', 'được', 'ok đi', 'chạy đi', 'làm đi', 'tiếp', 'xác nhận', 'ừ', 'ừm', 'đúng rồi', 'chốt'];
        $rejectWords = ['no', 'cancel', 'stop', 'không', 'hủy', 'thôi', 'dừng', 'bỏ'];

        $isConfirm = false;
        $isReject = false;

        foreach ($confirmWords as $word) {
            if (str_contains($normalizedText, $word)) {
                $isConfirm = true;
                break;
            }
        }

        if (!$isConfirm) {
            foreach ($rejectWords as $word) {
                if (str_contains($normalizedText, $word)) {
                    $isReject = true;
                    break;
                }
            }
        }

        if ($isReject) {
            $this->deleteSession($sessionKey);
            $this->sendTelegram($botToken, $chatId, "❌ Đã hủy. Gửi lại khi bạn sẵn sàng.");
            return;
        }

        if ($isConfirm) {
            // Trigger the workflow
            $runId = $this->triggerWorkflow($botToken, $chatId, $session);
            $this->deleteSession($sessionKey);
            if ($runId) {
                $this->sendTelegram($botToken, $chatId, "🚀 Đang xử lý workflow...\n\n🆔 Run ID: `{$runId}`");
            } else {
                $this->sendTelegram($botToken, $chatId, "❌ Không tìm thấy workflow phù hợp.");
            }
            return;
        }

        // Not clear — treat as additional info, re-buffer
        $session['texts'][] = $text;
        $session['status'] = 'awaiting_confirmation';
        $this->saveSession($sessionKey, $session);

        $this->sendTelegram($botToken, $chatId, "Mình chưa hiểu. Reply *ok* để xác nhận hoặc *hủy* để bỏ.");
    }

    /**
     * Trigger the actual workflow with the collected session data.
     */
    public function triggerWorkflow(string $botToken, string $chatId, array $session): ?string
    {
        $workflow = $this->findWorkflowByBotToken($botToken);

        if ($workflow === null) {
            Log::warning('No workflow found for bot token during trigger', [
                'botToken' => mb_substr($botToken, 0, 8) . '***',
            ]);
            return null;
        }

        // Download images from Telegram to get URLs
        $imageUrls = [];
        foreach ($session['images'] ?? [] as $img) {
            $fileId = $img['file_id'] ?? '';
            if ($fileId) {
                $url = $this->getTelegramFileUrl($botToken, $fileId);
                if ($url) {
                    $imageUrls[] = $url;
                }
            }
        }

        // Also extract URLs from text
        $allText = implode("\n", $session['texts'] ?? []);
        if (preg_match_all('/https?:\/\/[^\s<>"]+\.(?:jpg|jpeg|png|webp|gif)/i', $allText, $urlMatches)) {
            $imageUrls = array_merge($imageUrls, $urlMatches[0]);
        }

        // Build combined trigger payload
        $combinedPayload = [
            'message' => [
                'chat' => ['id' => (int) $chatId, 'type' => $session['messages'][0]['chat']['type'] ?? 'group'],
                'from' => $session['messages'][0]['from'] ?? [],
                'date' => time(),
                'text' => $allText,
                'message_id' => $session['messages'][0]['message_id'] ?? 0,
            ],
            '_intake' => [
                'textParts' => $session['texts'] ?? [],
                'imageUrls' => array_values(array_unique($imageUrls)),
                'imageCount' => count($imageUrls),
                'messageCount' => count($session['messages'] ?? []),
                'combinedText' => $allText,
                'startedAt' => $session['startedAt'] ?? now()->toIso8601String(),
                'confirmedAt' => now()->toIso8601String(),
            ],
            'update_id' => 0,
        ];

        $document = $workflow->document;
        $document = $this->injectTriggerPayload($document, $combinedPayload, $botToken);

        $documentHash = hash('sha256', json_encode($document, JSON_THROW_ON_ERROR));

        $nodeConfigHashes = [];
        foreach ($document['nodes'] ?? [] as $node) {
            $config = $node['data']['config'] ?? $node['config'] ?? [];
            $nodeConfigHashes[$node['id']] = hash('sha256', json_encode($config, JSON_THROW_ON_ERROR));
        }

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'telegramWebhook',
            'target_node_id' => null,
            'status' => 'pending',
            'document_snapshot' => $document,
            'document_hash' => $documentHash,
            'node_config_hashes' => $nodeConfigHashes,
        ]);

        RunWorkflowJob::dispatch($run->id);

        Log::info('Workflow triggered from Telegram intake', [
            'runId' => $run->id,
            'chatId' => $chatId,
            'textParts' => count($session['texts'] ?? []),
            'images' => count($imageUrls),
        ]);

        return $run->id;
    }

    /**
     * Get a downloadable URL for a Telegram file.
     */
    private function getTelegramFileUrl(string $botToken, string $fileId): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::get(
                "https://api.telegram.org/bot{$botToken}/getFile",
                ['file_id' => $fileId]
            );

            $filePath = $response->json('result.file_path');
            if ($filePath) {
                return "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to get Telegram file URL', ['fileId' => $fileId, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Send a text message via Telegram Bot API.
     */
    private function sendTelegram(string $botToken, string $chatId, string $text): void
    {
        try {
            \Illuminate\Support\Facades\Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => mb_substr($text, 0, 4096),
                    'parse_mode' => 'Markdown',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send Telegram message', ['chatId' => $chatId, 'error' => $e->getMessage()]);
        }
    }

    // ─── Session management (Redis) ───────────────────────────

    private function getSession(string $key): ?array
    {
        $data = Redis::get($key);
        return $data ? json_decode($data, true) : null;
    }

    private function saveSession(string $key, array $session): void
    {
        Redis::set($key, json_encode($session, JSON_THROW_ON_ERROR), 'EX', self::BUFFER_TTL);
    }

    private function deleteSession(string $key): void
    {
        Redis::del($key);
    }

    // ─── Existing methods (preserved) ─────────────────────────

    private function parseUpdate(Request $request): array
    {
        $update = $request->all();

        if (empty($update)) {
            $rawBody = $request->getContent();
            if ($rawBody) {
                $update = json_decode($rawBody, true) ?? [];
            }
        }

        if (empty($update)) {
            $phpInput = file_get_contents('php://input');
            if ($phpInput) {
                $update = json_decode($phpInput, true) ?? [];
            }
        }

        if (empty($update)) {
            $jsonData = $request->json()->all();
            if (!empty($jsonData)) {
                $update = $jsonData;
            }
        }

        return $update;
    }

    private function findWorkflowByBotToken(string $botToken): ?Workflow
    {
        $workflows = Workflow::all();

        foreach ($workflows as $workflow) {
            $document = $workflow->document;
            $nodes = $document['nodes'] ?? [];

            foreach ($nodes as $node) {
                $config = $node['data']['config'] ?? $node['config'] ?? [];
                if (($node['type'] ?? '') === 'telegramTrigger'
                    && ($config['botToken'] ?? '') === $botToken) {
                    return $workflow;
                }
            }
        }

        return null;
    }

    private function tryResumeGate(string $botToken, string $chatId, string $text, array $update): bool
    {
        if (empty($chatId)) {
            return false;
        }

        $runs = ExecutionRun::where('status', 'awaitingReview')->get();

        foreach ($runs as $run) {
            $document = $run->document_snapshot ?? [];
            $nodes = $document['nodes'] ?? [];

            foreach ($nodes as $node) {
                if (($node['type'] ?? '') !== 'humanGate') {
                    continue;
                }

                $config = $node['data']['config'] ?? $node['config'] ?? [];
                $gateChannel = $config['channel'] ?? 'ui';
                $gateChatId = $config['chatId'] ?? '';

                if (!in_array($gateChannel, ['telegram', 'any'])) {
                    continue;
                }
                if ($gateChatId !== $chatId) {
                    continue;
                }

                $record = NodeRunRecord::where('run_id', $run->id)
                    ->where('node_id', $node['id'])
                    ->where('status', 'awaitingReview')
                    ->first();

                if ($record === null) {
                    continue;
                }

                $updatedDoc = $document;
                foreach ($updatedDoc['nodes'] as &$n) {
                    if ($n['id'] === $node['id']) {
                        if (isset($n['data']['config'])) {
                            $n['data']['config']['_gateResponse'] = $text ?: json_encode($update);
                        } else {
                            $n['config']['_gateResponse'] = $text ?: json_encode($update);
                        }
                    }
                }

                $record->update([
                    'status' => 'success',
                    'output_payloads' => [
                        'response' => [
                            'value' => $text,
                            'status' => 'success',
                            'schemaType' => 'json',
                        ],
                    ],
                    'completed_at' => now(),
                ]);

                $run->update([
                    'status' => 'pending',
                    'document_snapshot' => $updatedDoc,
                ]);

                RunWorkflowJob::dispatch($run->id);

                Log::info('HumanGate resumed via Telegram', [
                    'runId' => $run->id,
                    'nodeId' => $node['id'],
                    'chatId' => $chatId,
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Handle Telegram inline keyboard callback (approve/reject buttons).
     * callback_data format: g:{runId}:{nodeId}:a|r
     */
    private function handleCallbackQuery(string $botToken, array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $callbackId = $callbackQuery['id'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $originalText = $callbackQuery['message']['text'] ?? '';

        // Parse callback_data: g:{runId}:{nodeId}:a|r
        if (!preg_match('/^g:([^:]+):([^:]+):(a|r)$/', $data, $matches)) {
            $this->answerCallback($botToken, $callbackId, '❓ Invalid callback');
            return;
        }

        $runId = $matches[1];
        $nodeId = $matches[2];
        $action = $matches[3]; // 'a' = approve, 'r' = reject

        $run = ExecutionRun::find($runId);
        if ($run === null || $run->status !== 'awaitingReview') {
            $this->answerCallback($botToken, $callbackId, '⏳ This run is no longer awaiting review');
            return;
        }

        $record = NodeRunRecord::where('run_id', $runId)
            ->where('node_id', $nodeId)
            ->where('status', 'awaitingReview')
            ->first();

        if ($record === null) {
            $this->answerCallback($botToken, $callbackId, '⏳ This gate has already been resolved');
            return;
        }

        $isApprove = $action === 'a';
        $responseText = $isApprove ? 'approved' : 'rejected';

        if ($isApprove) {
            // Inject _gateResponse into document snapshot and resume
            $document = $run->document_snapshot ?? [];
            foreach ($document['nodes'] as &$n) {
                if ($n['id'] === $nodeId) {
                    if (isset($n['data']['config'])) {
                        $n['data']['config']['_gateResponse'] = $responseText;
                    } else {
                        $n['config']['_gateResponse'] = $responseText;
                    }
                }
            }

            $record->update([
                'status' => 'success',
                'output_payloads' => [
                    'response' => [
                        'value' => $responseText,
                        'status' => 'success',
                        'schemaType' => 'json',
                    ],
                ],
                'completed_at' => now(),
            ]);

            $run->update([
                'status' => 'pending',
                'document_snapshot' => $document,
            ]);

            RunWorkflowJob::dispatch($run->id);

            Log::info('HumanGate approved via inline button', [
                'runId' => $runId,
                'nodeId' => $nodeId,
                'chatId' => $chatId,
            ]);
        } else {
            // Reject — mark node as error, let deriveTerminalStatus handle run status
            $record->update([
                'status' => 'error',
                'error_message' => 'Rejected by user via Telegram',
                'completed_at' => now(),
            ]);

            $run->update([
                'status' => 'error',
                'completed_at' => now(),
                'termination_reason' => 'gate_rejected',
            ]);

            Log::info('HumanGate rejected via inline button', [
                'runId' => $runId,
                'nodeId' => $nodeId,
                'chatId' => $chatId,
            ]);
        }

        // Answer the callback to dismiss the loading spinner
        $emoji = $isApprove ? '✅' : '❌';
        $this->answerCallback($botToken, $callbackId, "{$emoji} " . ucfirst($responseText));

        // Edit the original message to show the decision (remove buttons)
        if ($messageId) {
            $updatedText = $originalText . "\n\n" . ($isApprove
                ? "✅ **Approved** — workflow resuming..."
                : "❌ **Rejected** — workflow stopped.");

            try {
                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/editMessageText", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => mb_substr($updatedText, 0, 4096),
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to edit gate message', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Answer a Telegram callback query (dismisses the loading spinner on the button).
     */
    private function answerCallback(string $botToken, string $callbackId, string $text = ''): void
    {
        try {
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
                'callback_query_id' => $callbackId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to answer callback query', ['error' => $e->getMessage()]);
        }
    }

    private function injectTriggerPayload(array $document, array $update, string $botToken): array
    {
        $nodes = $document['nodes'] ?? [];

        foreach ($nodes as &$node) {
            $config = $node['data']['config'] ?? $node['config'] ?? [];
            if (($node['type'] ?? '') === 'telegramTrigger'
                && ($config['botToken'] ?? '') === $botToken) {
                if (isset($node['data']['config'])) {
                    $node['data']['config']['_triggerPayload'] = $update;
                } else {
                    $node['config']['_triggerPayload'] = $update;
                }
            }
        }

        $document['nodes'] = $nodes;

        return $document;
    }
}
