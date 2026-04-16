<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PendingInteraction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PendingInteractionTest extends TestCase
{
    #[Test]
    public function fillable_includes_all_expected_fields(): void
    {
        $model = new PendingInteraction();

        $expected = [
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

        $this->assertSame($expected, $model->getFillable());
    }

    #[Test]
    public function casts_are_correctly_defined(): void
    {
        $model = new PendingInteraction();
        $casts = $model->getCasts();

        $this->assertSame('array', $casts['proposal_payload']);
        $this->assertSame('array', $casts['response_payload']);
        $this->assertSame('array', $casts['node_state']);
        $this->assertSame('datetime', $casts['responded_at']);
    }

    #[Test]
    public function is_waiting_returns_true_when_status_is_waiting(): void
    {
        $model = new PendingInteraction();
        $model->status = 'waiting';

        $this->assertTrue($model->isWaiting());
    }

    #[Test]
    public function is_waiting_returns_false_when_status_is_responded(): void
    {
        $model = new PendingInteraction();
        $model->status = 'responded';

        $this->assertFalse($model->isWaiting());
    }

    #[Test]
    public function is_waiting_returns_false_when_status_is_expired(): void
    {
        $model = new PendingInteraction();
        $model->status = 'expired';

        $this->assertFalse($model->isWaiting());
    }

    #[Test]
    public function model_uses_uuid_primary_key(): void
    {
        $model = new PendingInteraction();

        $this->assertSame('id', $model->getKeyName());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->getIncrementing());
    }
}
