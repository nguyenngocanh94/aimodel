<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\TelegramAgent\Tools\CancelRunTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\StringType;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CancelRunToolTest extends TestCase
{
    use RefreshDatabase;

    private function makeRun(array $overrides = []): ExecutionRun
    {
        $workflow = Workflow::create([
            'name'     => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        return ExecutionRun::create(array_merge([
            'workflow_id'        => $workflow->id,
            'trigger'            => 'telegramWebhook',
            'status'             => 'running',
            'document_snapshot'  => ['nodes' => [], 'edges' => []],
            'document_hash'      => 'abc123',
            'node_config_hashes' => [],
        ], $overrides));
    }

    #[Test]
    public function description_returns_non_empty_string(): void
    {
        $this->assertNotEmpty((new CancelRunTool())->description());
    }

    #[Test]
    public function schema_has_run_id_string_required(): void
    {
        $schema = (new CancelRunTool())->schema(new JsonSchemaTypeFactory());

        $this->assertArrayHasKey('runId', $schema);
        $this->assertInstanceOf(StringType::class, $schema['runId']);
    }

    #[Test]
    public function cancellable_run_is_updated_to_cancelled(): void
    {
        $run = $this->makeRun(['status' => 'running']);

        $result = json_decode((new CancelRunTool())->handle(new Request(['runId' => $run->id])), true);

        $this->assertSame($run->id, $result['runId']);
        $this->assertSame('cancelled', $result['status']);

        $freshRun = ExecutionRun::find($run->id);
        $this->assertSame('cancelled', $freshRun->status);
        $this->assertSame('userCancelled', $freshRun->termination_reason);
        $this->assertNotNull($freshRun->completed_at);
    }

    #[Test]
    public function pending_run_can_be_cancelled(): void
    {
        $run = $this->makeRun(['status' => 'pending']);

        $result = json_decode((new CancelRunTool())->handle(new Request(['runId' => $run->id])), true);

        $this->assertSame('cancelled', $result['status']);
    }

    #[Test]
    public function awaiting_review_run_can_be_cancelled(): void
    {
        $run = $this->makeRun(['status' => 'awaitingReview']);

        $result = json_decode((new CancelRunTool())->handle(new Request(['runId' => $run->id])), true);

        $this->assertSame('cancelled', $result['status']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function terminalStatusProvider(): array
    {
        return [
            'success'   => ['success'],
            'error'     => ['error'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[Test]
    #[DataProvider('terminalStatusProvider')]
    public function terminal_status_run_returns_not_cancellable(string $status): void
    {
        $run = $this->makeRun(['status' => $status]);

        $result = json_decode((new CancelRunTool())->handle(new Request(['runId' => $run->id])), true);

        $this->assertSame('not_cancellable', $result['error']);
        $this->assertSame($status, $result['status']);
    }

    #[Test]
    public function unknown_run_id_returns_run_not_found(): void
    {
        $result = json_decode((new CancelRunTool())->handle(new Request(['runId' => 'does-not-exist'])), true);

        $this->assertSame('run_not_found', $result['error']);
    }
}
