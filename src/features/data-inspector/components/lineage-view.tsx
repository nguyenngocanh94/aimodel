/**
 * LineageView - AiModel-1n1.3
 * Traces payload origin through the graph for a selected node.
 * Per plan section 6.5
 */

import { useMemo } from 'react'
import { ArrowRight } from 'lucide-react'
import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
} from '@/features/workflows/domain/workflow-types'

interface LineageStep {
  readonly nodeId: string
  readonly nodeLabel: string
  readonly nodeType: string
  readonly portKey: string
}

interface LineageViewProps {
  readonly nodeId: string
  readonly document: WorkflowDocument
}

/**
 * Trace the lineage (upstream chain) for each input port of a node.
 */
export function traceLineage(
  nodeId: string,
  document: WorkflowDocument,
): Map<string, readonly LineageStep[]> {
  const nodeMap = new Map(document.nodes.map((n) => [n.id, n]))
  const lineages = new Map<string, readonly LineageStep[]>()

  // Find all incoming edges to this node
  const incomingEdges = document.edges.filter((e) => e.targetNodeId === nodeId)

  for (const edge of incomingEdges) {
    const steps: LineageStep[] = []
    traceUpstream(edge.sourceNodeId, edge.sourcePortKey, nodeMap, document.edges, steps, new Set())
    lineages.set(edge.targetPortKey, steps)
  }

  return lineages
}

function traceUpstream(
  nodeId: string,
  portKey: string,
  nodeMap: Map<string, WorkflowNode>,
  edges: readonly WorkflowEdge[],
  steps: LineageStep[],
  visited: Set<string>,
): void {
  if (visited.has(nodeId)) return
  visited.add(nodeId)

  const node = nodeMap.get(nodeId)
  if (!node) return

  steps.unshift({ nodeId, nodeLabel: node.label, nodeType: node.type, portKey })

  // Find upstream edges to this node
  const incomingEdges = edges.filter((e) => e.targetNodeId === nodeId)
  if (incomingEdges.length > 0) {
    // Follow the first incoming edge (primary lineage)
    const firstEdge = incomingEdges[0]
    traceUpstream(firstEdge.sourceNodeId, firstEdge.sourcePortKey, nodeMap, edges, steps, visited)
  }
}

export function LineageView({ nodeId, document }: LineageViewProps) {
  const lineages = useMemo(
    () => traceLineage(nodeId, document),
    [nodeId, document],
  )

  if (lineages.size === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        No upstream connections. This node has no input lineage.
      </p>
    )
  }

  return (
    <div className="space-y-2">
      <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
        Input Lineage
      </h4>
      {[...lineages.entries()].map(([inputPort, steps]) => (
        <div key={inputPort} className="space-y-1">
          <span className="text-xs font-medium">{inputPort}</span>
          <div className="flex flex-wrap items-center gap-1 text-[10px]">
            {steps.map((step, i) => (
              <span key={step.nodeId} className="flex items-center gap-1">
                {i > 0 && <ArrowRight className="h-2.5 w-2.5 text-muted-foreground" />}
                <span className="bg-muted rounded px-1 py-0.5 font-mono">
                  {step.nodeLabel}
                  <span className="text-muted-foreground">.{step.portKey}</span>
                </span>
              </span>
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}
