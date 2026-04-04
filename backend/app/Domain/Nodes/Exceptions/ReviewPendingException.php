<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Exceptions;

use RuntimeException;

class ReviewPendingException extends RuntimeException
{
    public function __construct(
        public readonly string $nodeId,
        string $message = 'Execution paused: human review required',
    ) {
        parent::__construct($message);
    }
}
