<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\Exceptions\ReviewPendingException;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\ReviewCheckpointTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReviewCheckpointTemplateTest extends TestCase
{
    private ReviewCheckpointTemplate $template;

    protected function setUp(): void
    {
        $this->template = new ReviewCheckpointTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('reviewCheckpoint', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame(NodeCategory::Utility, $this->template->category);
    }

    #[Test]
    public function ports_has_json_input_and_json_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('data', $ports->inputs[0]->key);
        $this->assertSame(DataType::Json, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('data', $ports->outputs[0]->key);
        $this->assertSame(DataType::Json, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_throws_review_pending_when_not_approved(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-7',
            config: ['approved' => false],
            inputs: [
                'data' => PortPayload::success(['key' => 'value'], DataType::Json),
            ],
            runId: 'run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $this->expectException(ReviewPendingException::class);

        $this->template->execute($ctx);
    }

    #[Test]
    public function execute_throws_review_pending_with_correct_node_id(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-review-42',
            config: ['approved' => false],
            inputs: [
                'data' => PortPayload::success('test', DataType::Json),
            ],
            runId: 'run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        try {
            $this->template->execute($ctx);
            $this->fail('Expected ReviewPendingException');
        } catch (ReviewPendingException $e) {
            $this->assertSame('node-review-42', $e->nodeId);
        }
    }

    #[Test]
    public function execute_passes_data_through_when_approved(): void
    {
        $inputData = ['scenes' => [1, 2, 3], 'metadata' => 'test'];

        $ctx = new NodeExecutionContext(
            nodeId: 'node-8',
            config: ['approved' => true],
            inputs: [
                'data' => PortPayload::success($inputData, DataType::Json),
            ],
            runId: 'run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('data', $result);
        $this->assertTrue($result['data']->isSuccess());
        $this->assertSame(DataType::Json, $result['data']->schemaType);
        $this->assertSame($inputData, $result['data']->value);
    }

    #[Test]
    public function execute_throws_by_default_config(): void
    {
        $config = $this->template->defaultConfig();

        $ctx = new NodeExecutionContext(
            nodeId: 'node-9',
            config: $config,
            inputs: [
                'data' => PortPayload::success('anything', DataType::Json),
            ],
            runId: 'run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $this->expectException(ReviewPendingException::class);

        $this->template->execute($ctx);
    }
}
