/**
 * Type Compatibility Registry - AiModel-9wx.3
 * Implements compatibility matrix and coercion rules for DataType pairs
 * per plan sections 9.1-9.4
 */

import type { DataType, CompatibilityResult } from './workflow-types';

// ============================================================
// Compatibility Matrix Definition
// ============================================================

/**
 * Represents the compatibility relationship between two DataTypes
 */
type CompatibilityEntry = {
  readonly compatible: boolean;
  readonly coercionApplied: boolean;
  readonly severity: 'none' | 'warning' | 'error';
  readonly reason: string;
  readonly suggestedAdapterNodeType?: string;
};

/**
 * The full compatibility matrix between all DataType pairs.
 * Rows are source types, columns are target types.
 * 
 * Key principles:
 * - Exact match: compatible, no coercion needed
 * - Safe scalar-to-list: compatible with info/warning (e.g., text -> textList)
 * - List-to-scalar: incompatible (e.g., textList -> text)
 * - Structural mismatch: may suggest adapter nodes
 * - No silent destructive coercions
 */
const COMPATIBILITY_MATRIX: Readonly<Record<DataType, Readonly<Record<DataType, CompatibilityEntry>>>> = {
  // === SCALAR TYPES ===
  text: {
    text: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    textList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Auto-wrap single item into list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Different semantic types (text vs prompt)', suggestedAdapterNodeType: 'promptRefiner' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Different semantic types with list wrapping', suggestedAdapterNodeType: 'promptRefiner' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be directly used as script (different structure)', suggestedAdapterNodeType: 'scriptWriter' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be directly used as scene', suggestedAdapterNodeType: 'sceneSplitter' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be directly used as scene list', suggestedAdapterNodeType: 'sceneSplitter' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to audio plan', suggestedAdapterNodeType: 'ttsVoiceoverPlanner' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to audio asset' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to subtitle', suggestedAdapterNodeType: 'subtitleFormatter' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to video' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Text wrapped as JSON string' },
  },
  prompt: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt is not plain text (different semantics)' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to text list' },
    prompt: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    promptList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Auto-wrap single prompt into list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be directly used as script (different structure)', suggestedAdapterNodeType: 'scriptWriter' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be directly used as scene', suggestedAdapterNodeType: 'sceneSplitter' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be directly used as scene list', suggestedAdapterNodeType: 'sceneSplitter' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to image (use imageGenerator)' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to image asset (use imageGenerator)' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to image asset list (use imageGenerator)' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to audio plan (use ttsVoiceoverPlanner)' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to video (use videoComposer)' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Prompt wrapped as JSON string' },
  },
  script: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script is not plain text (structured content)' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to prompt list' },
    script: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be directly used as single scene (use sceneSplitter)', suggestedAdapterNodeType: 'sceneSplitter' },
    sceneList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Script converted to scene list via sceneSplitter logic', suggestedAdapterNodeType: 'sceneSplitter' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to audio plan (use ttsVoiceoverPlanner)' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to subtitle (use subtitleFormatter)', suggestedAdapterNodeType: 'subtitleFormatter' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to video (use videoComposer)' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Script cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Script wrapped as JSON string' },
  },
  scene: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene is structured, not plain text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to text list' },
    prompt: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Scene description extracted as prompt' },
    promptList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Scene description extracted as single-item prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Single scene cannot be converted to full script' },
    scene: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    sceneList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Single scene wrapped in list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to image asset (use imageGenerator)' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to video' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Scene wrapped as JSON string' },
  },
  imageFrame: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image is not a prompt (reverse: prompt -> image via generator)' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to scene list' },
    imageFrame: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    imageFrameList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Single frame wrapped in list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image frame cannot be directly used as asset (use imageAssetMapper)', suggestedAdapterNodeType: 'imageAssetMapper' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image frame cannot be converted to asset list (use imageAssetMapper)', suggestedAdapterNodeType: 'imageAssetMapper' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Single image frame cannot be video (use videoComposer)', suggestedAdapterNodeType: 'videoComposer' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Image metadata wrapped as JSON' },
  },
  imageAsset: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset cannot be converted to text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Asset is not a raw frame' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Asset cannot be converted to frame list' },
    imageAsset: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    imageAssetList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Single asset wrapped in list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be directly used as video (use videoComposer)', suggestedAdapterNodeType: 'videoComposer' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Image asset metadata wrapped as JSON' },
  },
  audioPlan: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan is not plain text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to image asset list' },
    audioPlan: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to audio asset (needs generation)' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to video' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio plan cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Audio plan wrapped as JSON' },
  },
  audioAsset: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset is not plain text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset is not a plan' },
    audioAsset: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be directly used as video (use videoComposer)', suggestedAdapterNodeType: 'videoComposer' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Audio asset cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Audio asset metadata wrapped as JSON' },
  },
  subtitleAsset: {
    text: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Subtitle text extracted as plain text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to audio' },
    subtitleAsset: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be directly used as video' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Subtitle cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Subtitle metadata wrapped as JSON' },
  },
  videoAsset: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video is not plain text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to single frame' },
    imageFrameList: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Video frames extracted as frame list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to single image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video audio track not directly accessible' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video subtitle track not directly accessible' },
    videoAsset: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Video cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Video metadata wrapped as JSON' },
  },
  reviewDecision: {
    text: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Review decision text extracted' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Review decision cannot be converted to video' },
    reviewDecision: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Review decision wrapped as JSON' },
  },
  json: {
    text: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'JSON stringified as text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON is not a prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to video' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'JSON cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
  },
  // === LIST TYPES ===
  textList: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'List cannot be destructively coerced to single item' },
    textList: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to image list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to video' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Text list cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Text list wrapped as JSON array' },
  },
  promptList: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'List cannot be destructively coerced to single item' },
    promptList: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to scene', suggestedAdapterNodeType: 'promptRefiner' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to scene list (use promptRefiner)', suggestedAdapterNodeType: 'promptRefiner' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to image list (use imageGenerator)', suggestedAdapterNodeType: 'imageGenerator' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to image asset list (use imageGenerator)', suggestedAdapterNodeType: 'imageGenerator' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to video' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Prompt list cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Prompt list wrapped as JSON array' },
  },
  sceneList: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to prompt list (use promptRefiner)', suggestedAdapterNodeType: 'promptRefiner' },
    script: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Scenes can be concatenated into script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'List cannot be destructively coerced to single item' },
    sceneList: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to image' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to image list (use imageGenerator)', suggestedAdapterNodeType: 'imageGenerator' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to image asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to image asset list' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to video (use videoComposer)', suggestedAdapterNodeType: 'videoComposer' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Scene list cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Scene list wrapped as JSON array' },
  },
  imageFrameList: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'List cannot be destructively coerced to single item' },
    imageFrameList: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Frame list cannot be converted to single asset' },
    imageAssetList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Frame list cannot be converted to asset list (use imageAssetMapper)', suggestedAdapterNodeType: 'imageAssetMapper' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Frame list cannot be directly used as video (use videoComposer)', suggestedAdapterNodeType: 'videoComposer' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image list cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Image list metadata wrapped as JSON' },
  },
  imageAssetList: {
    text: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to text' },
    textList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to text list' },
    prompt: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to prompt' },
    promptList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to prompt list' },
    script: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to script' },
    scene: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to scene' },
    sceneList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to scene list' },
    imageFrame: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Asset list cannot be converted to frame' },
    imageFrameList: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Asset list cannot be converted to frame list' },
    imageAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'List cannot be destructively coerced to single item' },
    imageAssetList: { compatible: true, coercionApplied: false, severity: 'none', reason: 'Exact type match' },
    audioPlan: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to audio plan' },
    audioAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to audio' },
    subtitleAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to subtitle' },
    videoAsset: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be directly used as video (use videoComposer)', suggestedAdapterNodeType: 'videoComposer' },
    reviewDecision: { compatible: false, coercionApplied: false, severity: 'error', reason: 'Image asset list cannot be converted to review decision' },
    json: { compatible: true, coercionApplied: true, severity: 'warning', reason: 'Image asset list metadata wrapped as JSON' },
  },
};

// ============================================================
// Public API
// ============================================================

/**
 * Check compatibility between source and target DataTypes.
 * Returns a CompatibilityResult with detailed information about
 * whether the connection is valid and what transformations may apply.
 * 
 * @param source - The source DataType (output port)
 * @param target - The target DataType (input port)
 * @returns CompatibilityResult with compatibility status and metadata
 */
export function checkCompatibility(
  source: DataType,
  target: DataType,
): CompatibilityResult {
  const entry = COMPATIBILITY_MATRIX[source]?.[target];
  
  if (!entry) {
    // This should never happen with the complete matrix, but handle defensively
    return {
      compatible: false,
      coercionApplied: false,
      severity: 'error',
      reason: `Unknown type combination: ${source} -> ${target}`,
    };
  }

  return {
    compatible: entry.compatible,
    coercionApplied: entry.coercionApplied,
    severity: entry.severity,
    reason: entry.reason,
    suggestedAdapterNodeType: entry.suggestedAdapterNodeType,
  };
}

/**
 * Get all compatible target types for a given source type.
 * Useful for filtering available input ports during connection.
 * 
 * @param source - The source DataType
 * @returns Array of compatible target DataTypes with their compatibility info
 */
export function getCompatibleTargets(
  source: DataType,
): ReadonlyArray<{ target: DataType; result: CompatibilityResult }> {
  const targets = COMPATIBILITY_MATRIX[source];
  if (!targets) return [];

  return Object.entries(targets)
    .filter(([, entry]) => entry.compatible)
    .map(([target, entry]) => ({
      target: target as DataType,
      result: {
        compatible: entry.compatible,
        coercionApplied: entry.coercionApplied,
        severity: entry.severity,
        reason: entry.reason,
        suggestedAdapterNodeType: entry.suggestedAdapterNodeType,
      },
    }));
}

/**
 * Check if a type is a list type (ends with "List").
 * Useful for UI filtering and validation logic.
 * 
 * @param type - The DataType to check
 * @returns True if the type is a list type
 */
export function isListType(type: DataType): boolean {
  return type.endsWith('List');
}

/**
 * Get the scalar (non-list) version of a list type.
 * Returns undefined if the type is not a list type.
 * 
 * @param listType - The list DataType
 * @returns The scalar DataType or undefined
 */
export function getScalarType(listType: DataType): DataType | undefined {
  if (!isListType(listType)) return undefined;
  return listType.slice(0, -4) as DataType;
}

/**
 * Get the list version of a scalar type.
 * Returns undefined if no corresponding list type exists.
 * 
 * @param scalarType - The scalar DataType
 * @returns The list DataType or undefined
 */
export function getListType(scalarType: DataType): DataType | undefined {
  if (isListType(scalarType)) return undefined;
  const listType = `${scalarType}List` as DataType;
  // Verify it exists in our type system
  const validTypes: DataType[] = [
    'text', 'textList', 'prompt', 'promptList', 'script', 'scene', 'sceneList',
    'imageFrame', 'imageFrameList', 'imageAsset', 'imageAssetList',
    'audioPlan', 'audioAsset', 'subtitleAsset', 'videoAsset',
    'reviewDecision', 'json'
  ];
  return validTypes.includes(listType) ? listType : undefined;
}
