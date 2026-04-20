<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\TelegramAgent\RedisConversationStore;
use App\Services\TelegramAgent\BehaviorSkills\BehaviorSkillComposer;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Contracts\ConversationStore;

class TelegramAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind our Redis-backed ConversationStore so RemembersConversations works.
        $this->app->singleton(ConversationStore::class, RedisConversationStore::class);
        $this->app->singleton(RedisConversationStore::class, RedisConversationStore::class);

        $this->app->singleton(BehaviorSkillComposer::class, BehaviorSkillComposer::class);
    }
}
