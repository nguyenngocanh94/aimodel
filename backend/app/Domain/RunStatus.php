<?php

declare(strict_types=1);

namespace App\Domain;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case AwaitingReview = 'awaitingReview';
    case Success = 'success';
    case Error = 'error';
    case Cancelled = 'cancelled';
    case Interrupted = 'interrupted';
}
