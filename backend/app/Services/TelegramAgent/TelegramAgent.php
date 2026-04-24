<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Models\Workflow;
use App\Services\Ai\Middleware\RetryPrimary;
use App\Services\Skills\SkillInclusionMode;
use App\Services\Skills\Skillable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
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
final class TelegramAgent implements Agent, Conversational, HasMiddleware, HasTools
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
     * Middleware pipeline (LP-H2/LP-H3): retry the primary provider on
     * 429/overloaded with bounded backoff before the vendor failover loop
     * advances to the next provider in config('ai.failover.text').
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RetryPrimary((int) config('ai.failover.primary_max_retry_seconds', 10))];
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
        // ComposeWorkflowTool / RefinePlanTool / PersistWorkflowTool are resolved
        // via the container's closure bindings, which read chatId/botToken from
        // the request-scoped TelegramAgentContext. TelegramAgentFactory::make()
        // populates that context before constructing this agent, so plain
        // `app(...)` resolves tools with the correct per-request identity.
        //
        // Do NOT pass [...] parameter overrides here — Laravel's container silently
        // drops them for closure bindings whose signature doesn't accept the second
        // $parameters argument.
        return [
            new RunWorkflowTool(chatId: $this->chatId),
            new ReplyTool(botToken: $this->botToken, chatId: $this->chatId),
            app(ComposeWorkflowTool::class),
            app(RefinePlanTool::class),
            app(PersistWorkflowTool::class),
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

        $photo    = $update['message']['photo'] ?? $update['channel_post']['photo'] ?? [];
        $document = $update['message']['document'] ?? $update['channel_post']['document'] ?? null;
        $hasImageDocument = is_array($document)
            && is_string($document['mime_type'] ?? null)
            && str_starts_with($document['mime_type'], 'image/');

        if ($this->chatId === '' || ($text === '' && $photo === [] && !$hasImageDocument)) {
            return;
        }

        // ── Slash command path ────────────────────────────────────────────────
        // Image-only / document-only updates have empty $text, so guard the index access.
        if ($text !== '' && $text[0] === '/') {
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

        // For image-only bursts, synthesize a hint so the LLM sees context.
        $promptText = $text !== '' ? $text : '[Người dùng gửi ' . count($photo) . ' ảnh không có chú thích]';

        // Load or create conversation memory keyed on chatId:botToken.
        $conversationUser = $this->makeConversationUser();
        $this->continueLastConversation($conversationUser);

        // LP-H3: use the ordered failover chain instead of a single provider.
        // `withModelFailover()` iterates the array; RetryPrimary middleware (below)
        // retries the primary on transient 429 / overloaded before failing over.
        $chain = (array) config('ai.failover.text', [config('ai.default')]);

        $response = $this->prompt(
            $promptText,
            provider: $chain,
        );
        // #region agent log
        $this->debugLog('initial', 'H10', 'TelegramAgent.php:198', 'telegram_agent_prompt_completed', [
            'chatId' => $this->chatId,
            'providerChain' => $chain,
            'promptTextPreview' => mb_substr($promptText, 0, 160),
            'responseTextPreview' => mb_substr((string) $response->text, 0, 200),
            'responseTextLength' => mb_strlen((string) $response->text),
        ]);
        // #endregion

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
        } catch (Throwable) {
            // no-op: debug logging must never affect runtime behavior
        }
    }
}
