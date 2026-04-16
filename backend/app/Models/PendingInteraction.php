<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PendingInteraction extends Model
{
    use HasUuids;

    protected $fillable = [
        'run_id',
        'node_id',
        'channel',
        'channel_message_id',
        'chat_id',
        'status',
        'proposal_payload',
        'response_payload',
        'node_state',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'proposal_payload' => 'array',
            'response_payload' => 'array',
            'node_state' => 'array',
            'responded_at' => 'datetime',
        ];
    }

    // ── Scopes ──

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', 'waiting');
    }

    public function scopeForChat(Builder $query, string $chatId): Builder
    {
        return $query->where('chat_id', $chatId);
    }

    public function scopeForMessage(Builder $query, string $messageId): Builder
    {
        return $query->where('channel_message_id', $messageId);
    }

    public function scopeForNode(Builder $query, string $runId, string $nodeId): Builder
    {
        return $query->where('run_id', $runId)->where('node_id', $nodeId);
    }

    // ── Helpers ──

    public function markResponded(array $responsePayload): void
    {
        $this->update([
            'status' => 'responded',
            'response_payload' => $responsePayload,
            'responded_at' => now(),
        ]);
    }

    public function markExpired(): void
    {
        $this->update([
            'status' => 'expired',
            'responded_at' => now(),
        ]);
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }
}
