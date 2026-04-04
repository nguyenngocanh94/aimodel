<?php

declare(strict_types=1);

namespace App\Domain;

enum RunTrigger: string
{
    case RunWorkflow = 'runWorkflow';
    case RunNode = 'runNode';
    case RunFromHere = 'runFromHere';
    case RunUpToHere = 'runUpToHere';
}
