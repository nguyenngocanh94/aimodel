<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

enum VibeImpact: string
{
    case Critical = 'critical';
    case Neutral = 'neutral';
}
