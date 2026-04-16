<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\HumanProposal;
use Tests\TestCase;

class HumanProposalTest extends TestCase
{
    public function test_construction_with_all_fields(): void
    {
        $proposal = new HumanProposal(
            message: 'Pick a version',
            channel: 'ui',
            payload: ['versions' => [1, 2, 3]],
            state: ['step' => 'review'],
        );

        $this->assertSame('Pick a version', $proposal->message);
        $this->assertSame('ui', $proposal->channel);
        $this->assertSame(['versions' => [1, 2, 3]], $proposal->payload);
        $this->assertSame(['step' => 'review'], $proposal->state);
    }

    public function test_construction_with_defaults(): void
    {
        $proposal = new HumanProposal(message: 'Approve?');

        $this->assertSame('Approve?', $proposal->message);
        $this->assertSame('telegram', $proposal->channel);
        $this->assertSame([], $proposal->payload);
        $this->assertSame([], $proposal->state);
    }

    public function test_to_array_includes_all_fields(): void
    {
        $proposal = new HumanProposal(
            message: 'Choose an option',
            channel: 'mcp',
            payload: ['options' => ['A', 'B']],
            state: ['iteration' => 2],
        );

        $this->assertSame([
            'message' => 'Choose an option',
            'channel' => 'mcp',
            'payload' => ['options' => ['A', 'B']],
            'state' => ['iteration' => 2],
        ], $proposal->toArray());
    }
}
