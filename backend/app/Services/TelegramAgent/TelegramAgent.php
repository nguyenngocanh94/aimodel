<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Models\Workflow;
use App\Services\Skills\SkillInclusionMode;
use App\Services\Skills\Skillable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use App\Services\TelegramAgent\Tools\ComposeWorkflowTool;
use App\Services\TelegramAgent\Tools\PersistWorkflowTool;
use App\Services\TelegramAgent\Tools\RefinePlanTool;
use App\Services\TelegramAgent\Tools\ReplyTool;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Throwable;

/**
 * First-party laravel/ai Agent that handles inbound Telegram updates.
 *
 * Each webhook hit constructs a fresh instance (not a singleton), sets
 * chatId + botToken, and calls handle(). Conversation memory is kept via
 * RemembersConversations + RedisConversationStore, keyed on
 * "{chatId}:{botToken}" so conversations are isolated per-chat and per-bot.
 *
 * Uses the {@see Skillable} trait for progressive-disclosure skill management.
 * Tool instructions come from resources/skills/{slug}/SKILL.md discovered by
 * the SkillRegistry. Full-mode tools (with constructor args) are returned by
 * {@see getSkillToolOverrides()}.
 */
final class TelegramAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;
    use Skillable;

    /**
     * Last inbound Telegram update passed to handle() — used when building instructions() for appliesTo().
     *
     * @var array<string, mixed>
     */
    private array $instructionContextUpdate = [];

    public function __construct(
        public string $chatId,
        public string $botToken,
        private SlashCommandRouter $slashRouter,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Agent contract
    // ─────────────────────────────────────────────────────────────────────────

    public function instructions(): string
    {
        $catalog = Workflow::triggerable()
            ->get(['slug', 'name', 'nl_description', 'param_schema'])
            ->toArray();

        $static = SystemPrompt::build($catalog, $this->chatId, $this->instructionContextUpdate);

        return $this->withSkillInstructions($static);
    }

    public function tools(): iterable
    {
        return $this->skillTools();
    }

    /**
     * Skill slugs the agent may invoke. Lite-mode skills are listed by name+desc
     * only; Full-mode skills are fully inlined and their tools appear in tools().
     *
     * @return iterable<string, SkillInclusionMode|string>
     */
    public function skills(): iterable
    {
        return [
            'list-workflows',
            'run-workflow',
            'get-run-status',
            'cancel-run',
            'reply',
            'compose-workflow',
            'catalog',
        ];
    }

    /**
     * Return manually-constructed Full-mode tools that need constructor args
     * (chatId, botToken) which the SkillRegistry's app()->make() cannot resolve.
     *
     * @return array<int, object>
     */
    protected function getSkillToolOverrides(): array
    {
        return [
            new RunWorkflowTool(chatId: $this->chatId),
            new ReplyTool(botToken: $this->botToken, chatId: $this->chatId),
            app()->make(ComposeWorkflowTool::class, [
                'chatId'   => $this->chatId,
                'botToken' => $this->botToken,
            ]),
            app()->make(RefinePlanTool::class, [
                'chatId'   => $this->chatId,
                'botToken' => $this->botToken,
            ]),
            app()->make(PersistWorkflowTool::class, [
                'chatId'   => $this->chatId,
                'botToken' => $this->botToken,
            ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Entry point
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle an inbound Telegram update.
     *
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update, string $botToken): void
    {
        $message        = $update['message'] ?? $update['channel_post'] ?? [];
        $this->chatId   = (string) data_get($message, 'chat.id', '');
        $this->botToken = $botToken;
        $text           = (string) data_get($message, 'text', data_get($message, 'caption', ''));

        if ($text === '' || $this->chatId === '') {
            return;
        }

        // ── Slash command path ────────────────────────────────────────────────
        if ($text[0] === '/') {
            $reply = $this->slashRouter->route($text, $this->chatId);

            if ($reply === '🔄 Session reset. (Storage cleared by caller.)') {
                $this->resetConversation();
                $reply = '🔄 Lịch sử hội thoại đã được xoá.';
            }

            if ($reply !== null) {
                $this->sendTelegramMessage($this->botToken, $this->chatId, $reply);
            }

            return;
        }

        // ── LLM path ─────────────────────────────────────────────────────────
        $this->instructionContextUpdate = $update;

        // Load or create conversation memory keyed on chatId:botToken.
        $conversationUser = $this->makeConversationUser();
        $this->continueLastConversation($conversationUser);

        $response = $this->prompt(
            $text,
            provider: config('ai.default'),
        );

        // ReplyTool sends explicit replies during tool execution.
        // Only forward $response->text if it's non-empty (end-of-turn narration).
        if ($response->text !== '') {
            $this->sendTelegramMessage($this->botToken, $this->chatId, $response->text);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a simple user-like object whose id is "{chatId}:{botToken}".
     * RemembersConversations uses $user->id as the key to retrieve/store conversations.
     */
    private function makeConversationUser(): object
    {
        return (object) ['id' => "{$this->chatId}:{$this->botToken}"];
    }

    /**
     * Wipe conversation memory for /reset.
     * Resets the in-memory trait state and deletes from the Redis store.
     */
    private function resetConversation(): void
    {
        $userId = "{$this->chatId}:{$this->botToken}";

        /** @var RedisConversationStore $store */
        $store = resolve(RedisConversationStore::class);
        $store->forgetUser($userId);

        // Reset in-memory state so this handle() call starts clean.
        $this->conversationId   = null;
        $this->conversationUser = null;
    }

    /**
     * Send a plain Telegram message, truncating to 4096 characters.
     * Errors are logged and swallowed — best-effort delivery.
     */
    private function sendTelegramMessage(string $botToken, string $chatId, string $text): void
    {
        try {
            Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id'    => $chatId,
                    'text'       => mb_substr($text, 0, 4096),
                    'parse_mode' => 'Markdown',
                ],
            );
        } catch (Throwable $e) {
            Log::warning('TelegramAgent: failed to send Telegram message', [
                'chatId' => $chatId,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
