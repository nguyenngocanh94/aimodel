<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\TelegramAgent\HandlesTelegramUpdate;
use App\Services\TelegramAgent\RedisConversationStore;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Contracts\ConversationStore;

class TelegramAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind our Redis-backed ConversationStore so RemembersConversations works.
        $this->app->singleton(ConversationStore::class, RedisConversationStore::class);
        $this->app->singleton(RedisConversationStore::class, RedisConversationStore::class);

        // TelegramAgent is per-request state (chatId/botToken live on it), not a singleton.
        // Rebind HandlesTelegramUpdate so the existing controller keeps resolving during LA2.
        // LA3 will rewrite the controller to construct TelegramAgent directly.
        $this->app->bind(HandlesTelegramUpdate::class, function () {
            return new TelegramAgent('', '', new SlashCommandRouter());
        });
    }
}
