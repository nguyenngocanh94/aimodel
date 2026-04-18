<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Models\ExecutionRun;
use App\Models\Workflow;

/**
 * Routes Telegram slash commands to deterministic (no-LLM) reply strings.
 *
 * Contract with TelegramAgent:
 * - Returns a non-null string  → caller sends it to Telegram and returns.
 * - Returns null               → $text is not a slash command; caller falls through to the LLM loop.
 *
 * /reset contract:
 *   This router returns a placeholder reply string for /reset but does NOT touch Redis.
 *   The TelegramAgent MUST call AgentSessionStore::forget($chatId, $botToken) before
 *   (or after) calling route(), then send the reply. The router is intentionally kept
 *   free of session/Redis dependencies so it can be tested without infrastructure.
 */
final class SlashCommandRouter
{
    /** Statuses that are still "live" and can be cancelled. */
    private const CANCELLABLE_STATUSES = ['pending', 'running', 'awaitingHuman', 'awaitingReview'];

    /**
     * Route a Telegram message to a reply string, or return null if not a slash command.
     *
     * @param  string  $text    Raw message text from Telegram.
     * @param  string  $chatId  Telegram chat ID (used for run ownership checks).
     * @return string|null  Reply text (plain or Markdown), or null to fall through to LLM.
     */
    public function route(string $text, string $chatId): ?string
    {
        $trimmed = trim($text);

        if ($trimmed === '' || $trimmed[0] !== '/') {
            return null;
        }

        // Split into command + optional arguments, normalise case.
        $parts = preg_split('/\s+/', $trimmed, 2);
        $cmd   = strtolower($parts[0] ?? '');
        $rest  = trim($parts[1] ?? '');

        return match ($cmd) {
            '/start'  => $this->handleStart(),
            '/help'   => $this->handleHelp(),
            '/list'   => $this->handleList(),
            '/status' => $this->handleStatus($rest),
            '/cancel' => $this->handleCancel($rest),
            '/reset'  => $this->handleReset(),
            default   => 'Lệnh không hợp lệ. Gõ /help để xem lệnh.',
        };
    }

    // -------------------------------------------------------------------------
    // Command handlers
    // -------------------------------------------------------------------------

    private function handleStart(): string
    {
        $list = $this->buildWorkflowList();

        return "👋 Xin chào! Tôi là AI Agent, hỗ trợ bạn chạy workflow tự động.\n\n" .
               "Các workflow có thể kích hoạt:\n" .
               $list . "\n\n" .
               "Gõ /help để xem hướng dẫn sử dụng.";
    }

    private function handleHelp(): string
    {
        return "📖 *Hướng dẫn sử dụng*\n\n" .
               "Lệnh có sẵn:\n" .
               "• /start — Chào mừng + danh sách workflow\n" .
               "• /list — Danh sách workflow có thể chạy\n" .
               "• /status — 5 run gần nhất của bạn\n" .
               "• /status <runId> — Chi tiết một run\n" .
               "• /cancel <runId> — Huỷ một run đang chạy\n" .
               "• /reset — Xoá lịch sử hội thoại\n\n" .
               "Hoặc nhắn tin tự do để tôi hiểu yêu cầu của bạn và chạy workflow phù hợp.";
    }

    private function handleList(): string
    {
        $list = $this->buildWorkflowList();

        return "📋 *Danh sách workflow có thể kích hoạt:*\n\n" . $list;
    }

    private function handleStatus(string $runId): string
    {
        if ($runId === '') {
            // List last 5 runs triggered by Telegram.
            $runs = ExecutionRun::where('trigger', 'telegramWebhook')
                ->orderByDesc('started_at')
                ->limit(5)
                ->get(['id', 'status', 'started_at', 'workflow_id']);

            if ($runs->isEmpty()) {
                return "ℹ️ chưa có run nào được tạo qua Telegram.";
            }

            $lines = $runs->map(function (ExecutionRun $run): string {
                $workflowName = $run->workflow_id
                    ? (Workflow::find($run->workflow_id)?->name ?? $run->workflow_id)
                    : '(unknown)';

                $shortName = mb_strimwidth($workflowName, 0, 40, '…');
                $at        = $run->started_at?->format('d/m/Y H:i') ?? '—';

                return "• `{$run->id}` — {$run->status} — {$shortName} — {$at}";
            })->implode("\n");

            return "📊 *5 run gần nhất:*\n\n" . $lines;
        }

        // Detail view for a specific run.
        // Guard against non-UUID strings that would cause a DB exception.
        if (! $this->isValidUuid($runId)) {
            return "❌ Không tìm thấy run `{$runId}`.";
        }

        $run = ExecutionRun::find($runId);

        if ($run === null) {
            return "❌ Không tìm thấy run `{$runId}`.";
        }

        $currentNode = $run->target_node_id ?? '—';
        $error       = $run->termination_reason ?? '—';
        $at          = $run->started_at?->format('d/m/Y H:i') ?? '—';

        return "🔍 *Chi tiết run:*\n\n" .
               "• Run ID: `{$run->id}`\n" .
               "• Status: {$run->status}\n" .
               "• Current node: {$currentNode}\n" .
               "• Bắt đầu: {$at}\n" .
               "• Lỗi / lý do kết thúc: {$error}";
    }

    private function handleCancel(string $runId): string
    {
        if ($runId === '') {
            return "⚠️ Vui lòng cung cấp runId. Ví dụ: /cancel <runId>";
        }

        if (! $this->isValidUuid($runId)) {
            return "❌ Không tìm thấy run `{$runId}`.";
        }

        $run = ExecutionRun::find($runId);

        if ($run === null) {
            return "❌ Không tìm thấy run `{$runId}`.";
        }

        if (! in_array($run->status, self::CANCELLABLE_STATUSES, true)) {
            return "⚠️ Run `{$runId}` ở trạng thái *{$run->status}* — không thể huỷ.";
        }

        $run->update([
            'status'             => 'cancelled',
            'termination_reason' => 'userCancelled',
        ]);

        return "✅ Run `{$runId}` đã được huỷ thành công.";
    }

    /**
     * Returns a placeholder reply for /reset.
     *
     * Storage (Redis session) must be cleared by the caller (TelegramAgent) via
     * AgentSessionStore::forget($chatId, $botToken). This router is stateless and
     * deliberately has no Redis dependency.
     */
    private function handleReset(): string
    {
        return "🔄 Session reset. (Storage cleared by caller.)";
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Returns true if $value matches the canonical UUID v4 format. */
    private function isValidUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        );
    }

    private function buildWorkflowList(): string
    {
        $workflows = Workflow::triggerable()->get(['slug', 'name', 'nl_description']);

        if ($workflows->isEmpty()) {
            return "_(Chưa có workflow nào được cấu hình)_";
        }

        return $workflows->map(function (Workflow $wf): string {
            $slug        = $wf->slug ?? '(no-slug)';
            $description = $wf->nl_description ?? $wf->name ?? '(no description)';

            return "• {$slug} — {$description}";
        })->implode("\n");
    }
}
