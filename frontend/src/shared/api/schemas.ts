import { z } from 'zod';

// ============================================================
// Port Payload Schema
// ============================================================

export const PortPayloadSchema = z.object({
  value: z.unknown().nullable(),
  status: z.enum(['idle', 'ready', 'running', 'success', 'error', 'skipped', 'cancelled']),
  schemaType: z.string(),
  producedAt: z.string().datetime().optional(),
  sourceNodeId: z.string().optional(),
  sourcePortKey: z.string().optional(),
  previewText: z.string().optional(),
  errorMessage: z.string().optional(),
  mimeType: z.string().optional(),
  artifactId: z.string().optional(),
});

export type PortPayload = z.infer<typeof PortPayloadSchema>;

// ============================================================
// Node Run Record Schema
// ============================================================

export const NodeRunRecordSchema = z.object({
  id: z.string().uuid(),
  runId: z.string().uuid(),
  nodeId: z.string(),
  status: z.enum(['pending', 'running', 'success', 'error', 'skipped', 'cancelled', 'awaitingReview']),
  skipReason: z.string().optional(),
  inputPayloads: z.record(z.string(), PortPayloadSchema).optional(),
  outputPayloads: z.record(z.string(), PortPayloadSchema).optional(),
  errorMessage: z.string().optional(),
  usedCache: z.boolean().optional(),
  durationMs: z.number().optional(),
});

export type NodeRunRecord = z.infer<typeof NodeRunRecordSchema>;

// ============================================================
// Execution Run Schema
// ============================================================

export const ExecutionRunSchema = z.object({
  id: z.string().uuid(),
  workflowId: z.string().uuid(),
  mode: z.string().optional(),
  trigger: z.enum(['runWorkflow', 'runNode', 'runFromHere', 'runUpToHere']),
  targetNodeId: z.string().optional(),
  plannedNodeIds: z.array(z.string()).optional(),
  status: z.enum(['pending', 'running', 'success', 'error', 'cancelled', 'awaitingReview', 'interrupted']),
  startedAt: z.string().datetime().optional(),
  completedAt: z.string().datetime().optional(),
  terminationReason: z.string().optional(),
  nodeRunRecords: z.array(NodeRunRecordSchema).optional(),
  summary: z.object({
    total: z.number(),
    success: z.number(),
    error: z.number(),
    skipped: z.number(),
  }).optional(),
});

export type ExecutionRun = z.infer<typeof ExecutionRunSchema>;

// ============================================================
// Execution Run List Schema (for paginated responses)
// ============================================================

export const ExecutionRunListSchema = z.object({
  data: z.array(ExecutionRunSchema),
  meta: z.object({
    currentPage: z.number(),
    lastPage: z.number(),
    perPage: z.number(),
    total: z.number(),
  }),
});

export type ExecutionRunList = z.infer<typeof ExecutionRunListSchema>;

// ============================================================
// Workflow Document Schemas
// ============================================================

export const WorkflowNodeSchema = z.object({
  id: z.string(),
  type: z.string(),
  label: z.string(),
  position: z.object({
    x: z.number(),
    y: z.number(),
  }),
  config: z.record(z.unknown()),
  disabled: z.boolean().optional(),
  notes: z.string().optional(),
});

export type WorkflowNode = z.infer<typeof WorkflowNodeSchema>;

export const WorkflowEdgeSchema = z.object({
  id: z.string(),
  sourceNodeId: z.string(),
  sourcePortKey: z.string(),
  targetNodeId: z.string(),
  targetPortKey: z.string(),
  targetOrder: z.number().optional(),
});

export type WorkflowEdge = z.infer<typeof WorkflowEdgeSchema>;

export const WorkflowDocumentSchema = z.object({
  id: z.string().uuid(),
  schemaVersion: z.number(),
  name: z.string(),
  description: z.string(),
  tags: z.array(z.string()),
  nodes: z.array(WorkflowNodeSchema),
  edges: z.array(WorkflowEdgeSchema),
  viewport: z.object({
    x: z.number(),
    y: z.number(),
    zoom: z.number(),
  }),
  createdAt: z.string().datetime(),
  updatedAt: z.string().datetime(),
  basedOnTemplateId: z.string().optional(),
  basedOnTemplateVersion: z.string().optional(),
});

export type WorkflowDocument = z.infer<typeof WorkflowDocumentSchema>;

// ============================================================
// Workflow Schema
// ============================================================

export const WorkflowSchema = z.object({
  id: z.string().uuid(),
  name: z.string(),
  description: z.string(),
  schemaVersion: z.number(),
  tags: z.array(z.string()),
  document: WorkflowDocumentSchema.optional(),
  createdAt: z.string().datetime(),
  updatedAt: z.string().datetime(),
});

export type Workflow = z.infer<typeof WorkflowSchema>;

// ============================================================
// Workflow List Schema (for paginated responses)
// ============================================================

export const WorkflowListSchema = z.object({
  data: z.array(WorkflowSchema),
  meta: z.object({
    currentPage: z.number(),
    lastPage: z.number(),
    perPage: z.number(),
    total: z.number(),
  }),
});

export type WorkflowList = z.infer<typeof WorkflowListSchema>;

// ============================================================
// Artifact Schema
// ============================================================

export const ArtifactSchema = z.object({
  id: z.string().uuid(),
  runId: z.string().uuid(),
  nodeId: z.string(),
  portKey: z.string(),
  mimeType: z.string(),
  sizeBytes: z.number(),
  path: z.string(),
  createdAt: z.string().datetime(),
});

export type Artifact = z.infer<typeof ArtifactSchema>;

// ============================================================
// API Error Schema
// ============================================================

export const ApiErrorSchema = z.object({
  error: z.string(),
  message: z.string().optional(),
  errors: z.record(z.array(z.string())).optional(),
});

export type ApiError = z.infer<typeof ApiErrorSchema>;
