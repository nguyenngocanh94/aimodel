<?php

declare(strict_types=1);

namespace App\Domain;

enum Capability: string
{
    case TextGeneration = 'text_generation';
    case TextToImage = 'text_to_image';
    case TextToSpeech = 'text_to_speech';
    case StructuredTransform = 'structured_transform';
    case MediaComposition = 'media_composition';
    case ReferenceToVideo = 'reference_to_video';
}
