<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Models\ExecutionRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class CancelRunTool implements Tool
{
    /** Terminal statuses that cannot be cancelled. */
    private const TERMINAL_STATUSES = ['success', 'error', 'cancelled'];

    public function description(): string
    {
        return 'Cancel a running or paused run by id.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'runId' => $schema->string()->description('The UUID of the execution run to cancel')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $runId = (string) $request->string('runId', '');

        // Guard against non-UUID strings that would cause a DB type error
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $runId)) {
            return json_encode(['error' => 'run_not_found'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $run = ExecutionRun::find($runId);

        if ($run === null) {
            return json_encode(['error' => 'run_not_found'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (in_array($run->status, self::TERMINAL_STATUSES, true)) {
            return json_encode(['error' => 'not_cancellable', 'status' => $run->status], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $run->update([
            'status'             => 'cancelled',
            'termination_reason' => 'userCancelled',
            'completed_at'       => now(),
        ]);

        return json_encode(['runId' => $run->id, 'status' => 'cancelled'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
