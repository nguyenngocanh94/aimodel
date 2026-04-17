<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Models\PendingInteraction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProposalSender
{
    public function send(PendingInteraction $pending, array $channelConfig = []): void
    {
        if ($pending->channel !== 'telegram') {
            return; // Only Telegram implemented for now
        }

        $botToken = $channelConfig['botToken'] ?? '';
        $chatId = $channelConfig['chatId'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            Log::warning('ProposalSender: missing Telegram config', [
                'run_id' => $pending->run_id,
                'node_id' => $pending->node_id,
            ]);
            return;
        }

        $proposal = $pending->proposal_payload ?? [];
        $message = $proposal['message'] ?? 'Awaiting your response';

        // Build inline keyboard if there are options
        $replyMarkup = ['force_reply' => true, 'selective' => true];

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "\xF0\x9F\x94\x94 " . mb_substr($message, 0, 4080),
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($replyMarkup),
            ]);

            $messageId = $response->json('result.message_id');

            if ($messageId) {
                $pending->update([
                    'channel_message_id' => (string) $messageId,
                    'chat_id' => $chatId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ProposalSender: failed to send Telegram message', [
                'error' => $e->getMessage(),
                'run_id' => $pending->run_id,
            ]);
        }
    }
}
