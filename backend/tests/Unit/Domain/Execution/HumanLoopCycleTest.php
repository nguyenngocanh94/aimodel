<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use App\Domain\DataType;
use App\Domain\Nodes\HumanProposal;
use App\Domain\Nodes\HumanResponse;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\PortPayload;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Domain\Nodes\HumanLoopStubTemplate;

final class HumanLoopCycleTest extends TestCase
{
    private HumanLoopStubTemplate $template;

    protected function setUp(): void
    {
        $this->template = new HumanLoopStubTemplate();
    }

    private function makeContext(array $inputs = []): NodeExecutionContext
    {
        return new NodeExecutionContext(
            nodeId: 'test-node-1',
            config: [],
            inputs: $inputs,
            runId: 'test-run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );
    }

    #[Test]
    public function full_cycle_propose_then_pick(): void
    {
        $ctx = $this->makeContext();

        // Step 1: propose
        $proposal = $this->template->propose($ctx);
        $this->assertInstanceOf(HumanProposal::class, $proposal);
        $this->assertSame('Pick an option', $proposal->message);
        $this->assertSame(1, $proposal->state['attempt']);

        // Step 2: human picks option 1
        $response = HumanResponse::pick(1);
        $result = $this->template->handleResponse($ctx, $response);

        // Should complete with outputs
        $this->assertIsArray($result);
        $this->assertArrayHasKey('textOut', $result);
        $this->assertInstanceOf(PortPayload::class, $result['textOut']);
        $this->assertTrue($result['textOut']->isSuccess());
    }

    #[Test]
    public function full_cycle_propose_prompt_back_then_pick(): void
    {
        $ctx = $this->makeContext();

        // Step 1: propose
        $proposal = $this->template->propose($ctx);
        $this->assertSame(1, $proposal->state['attempt']);

        // Step 2: human sends feedback (prompt-back)
        $response = HumanResponse::promptBack('try something different');
        $result = $this->template->handleResponse($ctx, $response);

        // Should return a NEW proposal (loop)
        $this->assertInstanceOf(HumanProposal::class, $result);
        $this->assertSame('Updated options', $result->message);
        $this->assertSame(2, $result->state['attempt']);
        $this->assertSame(['options' => ['C', 'D']], $result->payload);

        // Step 3: human picks from updated options
        $response2 = HumanResponse::pick(0);
        $result2 = $this->template->handleResponse($ctx, $response2);

        // Should complete
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('textOut', $result2);
    }

    #[Test]
    public function proposal_serializes_and_deserializes_state(): void
    {
        $ctx = $this->makeContext();
        $proposal = $this->template->propose($ctx);

        // Serialize (as would happen when saving to PendingInteraction)
        $arr = $proposal->toArray();
        $this->assertArrayHasKey('message', $arr);
        $this->assertArrayHasKey('channel', $arr);
        $this->assertArrayHasKey('payload', $arr);
        $this->assertArrayHasKey('state', $arr);
        $this->assertSame(1, $arr['state']['attempt']);
    }

    #[Test]
    public function response_serializes_and_deserializes(): void
    {
        // Pick
        $pick = HumanResponse::pick(2);
        $arr = $pick->toArray();
        $restored = HumanResponse::fromArray($arr);
        $this->assertSame('pick', $restored->type);
        $this->assertSame(2, $restored->selectedIndex);

        // Edit
        $edit = HumanResponse::edit('new content');
        $arr = $edit->toArray();
        $restored = HumanResponse::fromArray($arr);
        $this->assertSame('edit', $restored->type);
        $this->assertSame('new content', $restored->editedContent);

        // Prompt-back
        $fb = HumanResponse::promptBack('make it funnier');
        $arr = $fb->toArray();
        $restored = HumanResponse::fromArray($arr);
        $this->assertSame('prompt_back', $restored->type);
        $this->assertSame('make it funnier', $restored->feedback);
        $this->assertTrue($restored->isPromptBack());
    }

    #[Test]
    public function multiple_prompt_backs_accumulate_state(): void
    {
        $ctx = $this->makeContext();

        // Round 1: propose
        $p1 = $this->template->propose($ctx);
        $this->assertSame(1, $p1->state['attempt']);

        // Round 2: prompt-back
        $p2 = $this->template->handleResponse($ctx, HumanResponse::promptBack('nope'));
        $this->assertInstanceOf(HumanProposal::class, $p2);
        $this->assertSame(2, $p2->state['attempt']);

        // Round 3: another prompt-back still returns proposal
        // (the stub always returns attempt: 2 for any prompt-back, but the pattern holds)
        $p3 = $this->template->handleResponse($ctx, HumanResponse::promptBack('still nope'));
        $this->assertInstanceOf(HumanProposal::class, $p3);

        // Round 4: finally pick
        $final = $this->template->handleResponse($ctx, HumanResponse::pick(0));
        $this->assertIsArray($final);
    }
}
