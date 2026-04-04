<?php

declare(strict_types=1);

namespace App\Domain;

enum DataType: string
{
    case Text = 'text';
    case TextList = 'textList';
    case Prompt = 'prompt';
    case PromptList = 'promptList';
    case Script = 'script';
    case Scene = 'scene';
    case SceneList = 'sceneList';
    case ImageFrame = 'imageFrame';
    case ImageFrameList = 'imageFrameList';
    case ImageAsset = 'imageAsset';
    case ImageAssetList = 'imageAssetList';
    case AudioPlan = 'audioPlan';
    case AudioAsset = 'audioAsset';
    case SubtitleAsset = 'subtitleAsset';
    case VideoAsset = 'videoAsset';
    case ReviewDecision = 'reviewDecision';
    case Json = 'json';
}
