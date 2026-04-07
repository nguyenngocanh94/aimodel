/**
 * Core workflow types - AiModel-9wx.1
 * Defines the document model for AI Video Workflow Builder
 * All types are readonly/immutable per plan section 8
 */

// ============================================================
// 8.2 Data Types
// ============================================================

export type DataType =
  | 'text'
  | 'textList'
  | 'prompt'
  | 'promptList'
  | 'script'
  | 'scene'
  | 'sceneList'
  | 'imageFrame'
  | 'imageFrameList'
  | 'imageAsset'
  | 'imageAssetList'
  | 'audioPlan'
  | 'audioAsset'
  | 'subtitleAsset'
  | 'videoUrl'
  | 'videoUrlList'
  | 'videoAsset'
  | 'reviewDecision'
  | 'json';

// ============================================================
// 8.3 Port Definition
// ============================================================

export interface PortDefinition {
  readonly key: string;
  readonly label: string;
  readonly direction: 'input' | 'output';
  readonly dataType: DataType;
  readonly required: boolean;
  readonly multiple: boolean;
  readonly description?: string;
}

// ============================================================
// 8.4 Workflow Document Entities
// ============================================================

export interface WorkflowNode<TConfig = unknown> {
  readonly id: string;
  readonly type: string;
  readonly label: string;
  readonly position: {
    readonly x: number;
    readonly y: number;
  };
  readonly config: Readonly<TConfig>;
  readonly disabled?: boolean;
  readonly notes?: string;
}

export interface WorkflowEdge {
  readonly id: string;
  readonly sourceNodeId: string;
  readonly sourcePortKey: string;
  readonly targetNodeId: string;
  readonly targetPortKey: string;
  readonly targetOrder?: number;
}

export interface WorkflowDocument {
  readonly id: string;
  readonly schemaVersion: number;
  readonly name: string;
  readonly description: string;
  readonly tags: readonly string[];
  readonly nodes: readonly WorkflowNode[];
  readonly edges: readonly WorkflowEdge[];
  readonly viewport: {
    readonly x: number;
    readonly y: number;
    readonly zoom: number;
  };
  readonly createdAt: string;
  readonly updatedAt: string;
  readonly basedOnTemplateId?: string;
  readonly basedOnTemplateVersion?: string;
}

// ============================================================
// 8.5 Port Payload Wrapper
// ============================================================

export interface PortPayload<TValue = unknown> {
  readonly value: TValue | null;
  readonly status: 'idle' | 'ready' | 'running' | 'success' | 'error' | 'skipped' | 'cancelled';
  readonly schemaType: DataType;
  readonly producedAt?: string;
  readonly sourceNodeId?: string;
  readonly sourcePortKey?: string;
  readonly previewText?: string;
  readonly previewUrl?: string;
  readonly sizeBytesEstimate?: number;
  readonly errorMessage?: string;
}

// ============================================================
// 8.6 Validation Model
// ============================================================

export type ValidationSeverity = 'error' | 'warning' | 'info';

export interface ValidationIssue {
  readonly id: string;
  readonly severity: ValidationSeverity;
  readonly scope: 'workflow' | 'node' | 'edge' | 'port' | 'config';
  readonly message: string;
  readonly nodeId?: string;
  readonly edgeId?: string;
  readonly portKey?: string;
  readonly code:
    | 'cycleDetected'
    | 'missingRequiredInput'
    | 'incompatiblePortTypes'
    | 'configInvalid'
    | 'orphanNode'
    | 'disabledNode'
    | 'coercionApplied'
    | 'downstreamInvalidated';
  readonly suggestion?: string;
}

// ============================================================
// 8.7 Run Model
// ============================================================

export interface ExecutionRun {
  readonly id: string;
  readonly workflowId: string;
  readonly mode: 'mock';
  readonly trigger: 'runWorkflow' | 'runNode' | 'runFromHere' | 'runUpToHere';
  readonly targetNodeId?: string;
  readonly plannedNodeIds: readonly string[];
  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'cancelled' | 'interrupted';
  readonly startedAt: string;
  readonly completedAt?: string;
  readonly terminationReason?: 'completed' | 'nodeError' | 'userCancelled' | 'tabClosed' | 'recoveredAfterCrash';
  readonly documentHash: string;
  readonly nodeConfigHashes: Readonly<Record<string, string>>;
}

export interface NodeRunRecord {
  readonly runId: string;
  readonly nodeId: string;
  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'skipped' | 'cancelled';
  readonly skipReason?: 'disabled' | 'missingRequiredInputs' | 'upstreamFailed';
  readonly blockedByNodeIds?: readonly string[];
  readonly startedAt?: string;
  readonly completedAt?: string;
  readonly durationMs?: number;
  readonly inputPayloads: Readonly<Record<string, PortPayload>>;
  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
  readonly errorMessage?: string;
  readonly usedCache: boolean;
}

export interface EdgePayloadSnapshot {
  readonly edgeId: string;
  readonly sourcePayload: PortPayload;
  readonly transportedPayload: PortPayload;
  readonly coercionApplied?: string;
}

// ============================================================
// 8.8 Compatibility Model
// ============================================================

export interface CompatibilityResult {
  readonly compatible: boolean;
  readonly coercionApplied: boolean;
  readonly severity: 'none' | 'warning' | 'error';
  readonly reason?: string;
  readonly suggestedAdapterNodeType?: string;
}

// ============================================================
// 8.10 Crash Recovery Snapshot
// ============================================================

export interface WorkflowSnapshot {
  readonly id: string;
  readonly workflowId: string;
  readonly kind: 'autosave' | 'recovery';
  readonly savedAt: string;
  readonly document: WorkflowDocument;
  readonly interruptedRunId?: string;
  readonly activeRunSummary?: {
    readonly runId: string;
    readonly nodeId: string;
    readonly status: ExecutionRun['status'];
  };
}
