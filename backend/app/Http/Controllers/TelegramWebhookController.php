<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Nodes\HumanResponse;
use App\Jobs\ResumeWorkflowJob;
use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\PendingInteraction;
use App\Services\TelegramAgent\HandlesTelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(private readonly HandlesTelegramUpdate $agent) {}

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

        // Priority 1: Check for reply-to a pending interaction message
        $replyToMessageId = (string) ($update['message']['reply_to_message']['message_id'] ?? '');
        if ($replyToMessageId !== '' && $this->tryResumePendingByMessage($botToken, $chatId, $replyToMessageId, $text)) {
            return response()->json(['ok' => true]);
        }

        // Priority 1b: Check for bare text matching a single pending interaction in this chat
        if ($text !== '' && $this->tryResumePendingByChat($botToken, $chatId, $text)) {
            return response()->json(['ok' => true]);
        }

        // Priority 1c: Legacy — check if this is a text response to a suspended HumanGate
        if ($this->tryResumeGate($botToken, $chatId, $text, $update)) {
            return response()->json(['ok' => true]);
        }

        // All other messages — delegate to TelegramAgent
        $this->agent->handle($update, $botToken);

        return response()->json(['ok' => true]);
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
     * Handle Telegram inline keyboard callback.
     * Supports both new PendingInteraction-based routing and legacy approve/reject.
     * callback_data formats:
     *   - g:{runId}:{nodeId}:a|r          (legacy approve/reject)
     *   - g:{runId}:{nodeId}:pick:{index}  (new pick-by-index)
     */
    private function handleCallbackQuery(string $botToken, array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $callbackId = $callbackQuery['id'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $messageId = (string) ($callbackQuery['message']['message_id'] ?? '');
        $originalText = $callbackQuery['message']['text'] ?? '';

        // Try new PendingInteraction-based routing first
        if (preg_match('/^g:([^:]+):([^:]+):(?:pick:(\d+)|(a|r))$/', $data, $matches)) {
            $runId = $matches[1];
            $nodeId = $matches[2];

            // New format: pick by index
            if (!empty($matches[3])) {
                $response = HumanResponse::pick((int) $matches[3]);
                $this->dispatchResume($runId, $nodeId, $response);
                $this->answerCallback($botToken, $callbackId, "\xE2\x9C\x85 Selection received");
                $this->editMessageDecision($botToken, $chatId, $messageId, $originalText, 'Selected option ' . $matches[3]);
                return;
            }

            // Legacy format: approve/reject
            $action = $matches[4] ?? '';
            $isApprove = $action === 'a';

            // Check if there's a PendingInteraction for this (new flow)
            $pending = PendingInteraction::where('run_id', $runId)
                ->where('node_id', $nodeId)
                ->waiting()
                ->first();

            if ($pending) {
                $response = $isApprove
                    ? HumanResponse::pick(0)
                    : HumanResponse::promptBack('rejected');
                $this->dispatchResume($runId, $nodeId, $response);
                $emoji = $isApprove ? "\xE2\x9C\x85" : "\xE2\x9D\x8C";
                $label = $isApprove ? 'Approved' : 'Rejected';
                $this->answerCallback($botToken, $callbackId, "{$emoji} {$label}");
                $this->editMessageDecision($botToken, $chatId, $messageId, $originalText, "{$emoji} **{$label}**");
                return;
            }

            // Fallback: legacy flow (old runs with awaitingReview status)
            $this->handleLegacyCallbackQuery($botToken, $callbackId, $chatId, $messageId, $originalText, $runId, $nodeId, $isApprove);
            return;
        }

        $this->answerCallback($botToken, $callbackId, "\xE2\x9D\x93 Invalid callback");
    }

    /**
     * Legacy callback handler for runs still using awaitingReview status.
     */
    private function handleLegacyCallbackQuery(
        string $botToken,
        string $callbackId,
        string $chatId,
        string $messageId,
        string $originalText,
        string $runId,
        string $nodeId,
        bool $isApprove,
    ): void {
        $run = ExecutionRun::find($runId);
        if ($run === null || $run->status !== 'awaitingReview') {
            $this->answerCallback($botToken, $callbackId, "\xE2\x8F\xB3 This run is no longer awaiting review");
            return;
        }

        $record = NodeRunRecord::where('run_id', $runId)
            ->where('node_id', $nodeId)
            ->where('status', 'awaitingReview')
            ->first();

        if ($record === null) {
            $this->answerCallback($botToken, $callbackId, "\xE2\x8F\xB3 This gate has already been resolved");
            return;
        }

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

            Log::info('HumanGate approved via inline button (legacy)', [
                'runId' => $runId,
                'nodeId' => $nodeId,
                'chatId' => $chatId,
            ]);
        } else {
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

            Log::info('HumanGate rejected via inline button (legacy)', [
                'runId' => $runId,
                'nodeId' => $nodeId,
                'chatId' => $chatId,
            ]);
        }

        $emoji = $isApprove ? "\xE2\x9C\x85" : "\xE2\x9D\x8C";
        $this->answerCallback($botToken, $callbackId, "{$emoji} " . ucfirst($responseText));

        $this->editMessageDecision(
            $botToken,
            $chatId,
            $messageId,
            $originalText,
            $isApprove
                ? "\xE2\x9C\x85 **Approved** \xE2\x80\x94 workflow resuming..."
                : "\xE2\x9D\x8C **Rejected** \xE2\x80\x94 workflow stopped.",
        );
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

    /**
     * Route a reply-to message to its PendingInteraction.
     */
    private function tryResumePendingByMessage(string $botToken, string $chatId, string $messageId, string $text): bool
    {
        $pending = PendingInteraction::forMessage($messageId)->waiting()->first();

        if ($pending === null) {
            return false;
        }

        $response = $this->parseTextResponse($text);
        $this->dispatchResume($pending->run_id, $pending->node_id, $response);

        $this->sendTelegram($botToken, $chatId, "\xE2\x9C\x85 Response received \xE2\x80\x94 processing...");
        return true;
    }

    /**
     * Route a bare text message to the single pending interaction in this chat.
     * If multiple pending, ask user to reply-to the specific message.
     */
    private function tryResumePendingByChat(string $botToken, string $chatId, string $text): bool
    {
        $pendingCount = PendingInteraction::forChat($chatId)->waiting()->count();

        if ($pendingCount === 0) {
            return false;
        }

        if ($pendingCount > 1) {
            $this->sendTelegram($botToken, $chatId,
                "\xE2\x9A\xA0 Bạn có {$pendingCount} tasks đang chờ. Reply trực tiếp vào message cần trả lời.");
            return true; // consumed the message (don't fall through to agent)
        }

        // Exactly 1 pending — route to it
        $pending = PendingInteraction::forChat($chatId)->waiting()->first();
        $response = $this->parseTextResponse($text);
        $this->dispatchResume($pending->run_id, $pending->node_id, $response);

        $this->sendTelegram($botToken, $chatId, "\xE2\x9C\x85 Response received \xE2\x80\x94 processing...");
        return true;
    }

    /**
     * Parse free-text into a HumanResponse.
     * Simple heuristic: if it looks like a number, it's a pick. Otherwise it's prompt_back.
     */
    private function parseTextResponse(string $text): HumanResponse
    {
        $trimmed = trim($text);

        // "1", "2", "3" => pick by index (0-based)
        if (preg_match('/^\d+$/', $trimmed) && (int) $trimmed > 0) {
            return HumanResponse::pick((int) $trimmed - 1); // Convert 1-based to 0-based
        }

        // Everything else is prompt-back feedback
        return HumanResponse::promptBack($trimmed);
    }

    /**
     * Dispatch a ResumeWorkflowJob for the given run/node/response.
     */
    private function dispatchResume(string $runId, string $nodeId, HumanResponse $response): void
    {
        ResumeWorkflowJob::dispatch($runId, $nodeId, $response->toArray());
    }

    /**
     * Edit a Telegram message to show the decision (remove buttons).
     */
    private function editMessageDecision(string $botToken, string $chatId, string $messageId, string $originalText, string $decision): void
    {
        if (empty($messageId)) {
            return;
        }

        $updatedText = $originalText . "\n\n" . $decision . " \xE2\x80\x94 workflow resuming...";

        try {
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => mb_substr($updatedText, 0, 4096),
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to edit message', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send a text message via Telegram Bot API.
     * Used by tryResumePendingByMessage, tryResumePendingByChat, and tryResumePendingByChat warning.
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
}
