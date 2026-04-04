<?php

declare(strict_types=1);

namespace App\Domain;

enum NodeCategory: string
{
    case Input = 'input';
    case Script = 'script';
    case Visuals = 'visuals';
    case Audio = 'audio';
    case Video = 'video';
    case Utility = 'utility';
    case Output = 'output';
}
