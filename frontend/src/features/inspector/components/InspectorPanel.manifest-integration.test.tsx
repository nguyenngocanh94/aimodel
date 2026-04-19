/**
 * NodeConfigTab manifest-integration tests — NM3
 *
 * Verifies that NodeConfigTab renders JsonSchemaForm when a manifest entry
 * is provided, and falls back to the loading skeleton when no entry exists.
 *
 * Uses a stubbed ManifestContext.Provider so the real fetch never fires.
 * Stubs the workflow store's commitAuthoring via vi.mock.
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import { ManifestContext } from '@/features/node-registry/manifest/manifest-context';
import type { ManifestContextValue } from '@/features/node-registry/manifest/manifest-context';
import type { ManifestResponse } from '@/features/node-registry/manifest/types';
import { NodeConfigTab } from './node-config-tab';
import type { WorkflowNode } from '@/features/workflows/domain/workflow-types';

// ────────────────────────────────────────────────────────────────
// Stub Zustand store
// ────────────────────────────────────────────────────────────────

const mockCommitAuthoring = vi.fn();

vi.mock('@/features/workflow/store/workflow-store', () => ({
  useWorkflowStore: (selector: (s: { commitAuthoring: () => void }) => unknown) =>
    selector({ commitAuthoring: mockCommitAuthoring }),
}));

// ────────────────────────────────────────────────────────────────
// Stub manifest fixture — minimal storyWriter entry
// ────────────────────────────────────────────────────────────────

const STORY_WRITER_MANIFEST: ManifestResponse = {
  version: 'test-version',
  nodes: {
    storyWriter: {
      type: 'storyWriter',
      version: '1.0.0',
      title: 'Story Writer',
      description: 'Story writer node',
      category: 'script',
      ports: { inputs: [], outputs: [] },
      configSchema: {
        $schema: 'http://json-schema.org/draft-07/schema#',
        type: 'object',
        properties: {
          targetDurationSeconds: {
            type: 'integer',
            minimum: 15,
            maximum: 120,
            default: 30,
            description: 'Target TVC duration in seconds',
          },
          storyFormula: {
            type: 'string',
            enum: ['hero_journey', 'problem_agitation_solution'],
            default: 'problem_agitation_solution',
            description: 'Story formula',
          },
          humanGate: {
            type: 'object',
            description: 'Human Gate configuration',
            properties: {
              enabled: {
                type: 'boolean',
                default: false,
                description: 'Enable human gate',
              },
              channel: {
                type: 'string',
                enum: ['ui', 'telegram', 'mcp', 'any'],
                default: 'ui',
                description: 'Notification channel',
              },
            },
            required: ['enabled'],
            additionalProperties: false,
          },
        },
        required: ['targetDurationSeconds', 'storyFormula'],
        additionalProperties: false,
      },
      defaultConfig: {
        targetDurationSeconds: 30,
        storyFormula: 'problem_agitation_solution',
        humanGate: { enabled: false, channel: 'ui' },
      },
      humanGateEnabled: true,
      executable: true,
    },
  },
};

// ────────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────────

function makeReadyContext(manifest: ManifestResponse): ManifestContextValue {
  return {
    status: 'ready',
    manifest,
    refetch: vi.fn().mockResolvedValue(undefined),
  };
}

function makeLoadingContext(): ManifestContextValue {
  return {
    status: 'loading',
    manifest: undefined,
    refetch: vi.fn().mockResolvedValue(undefined),
  };
}

const STORY_NODE: WorkflowNode = {
  id: 'node-sw-1',
  type: 'storyWriter',
  label: 'Story Writer',
  position: { x: 0, y: 0 },
  config: {
    targetDurationSeconds: 30,
    storyFormula: 'problem_agitation_solution',
    humanGate: { enabled: false, channel: 'ui' },
  },
};

function renderWithManifest(
  manifestCtx: ManifestContextValue,
  node: WorkflowNode = STORY_NODE,
) {
  return render(
    <ManifestContext.Provider value={manifestCtx}>
      <NodeConfigTab node={node} />
    </ManifestContext.Provider>,
  );
}

// ────────────────────────────────────────────────────────────────
// Tests
// ────────────────────────────────────────────────────────────────

describe('NodeConfigTab manifest integration', () => {
  it('renders JsonSchemaForm fields when manifest entry is available', () => {
    renderWithManifest(makeReadyContext(STORY_WRITER_MANIFEST));

    // targetDurationSeconds → number input
    expect(screen.getByLabelText(/target duration seconds/i)).toBeInTheDocument();
    // storyFormula → select
    expect(screen.getByDisplayValue('problem_agitation_solution')).toBeInTheDocument();
  });

  it('renders the humanGate object as a collapsible fieldset with its children', () => {
    renderWithManifest(makeReadyContext(STORY_WRITER_MANIFEST));

    // ObjectField renders a <details><summary>Human Gate</summary>
    expect(screen.getByText('Human Gate')).toBeInTheDocument();
    // nested enabled switch and channel select should be present
    expect(screen.getAllByRole('switch').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByDisplayValue('ui')).toBeInTheDocument();
  });

  it('shows loading skeleton when manifest entry is missing (loading state)', () => {
    renderWithManifest(makeLoadingContext());

    // Pilot template has no local configSchema, so loading skeleton shows
    expect(screen.getByText(/đang tải schema/i)).toBeInTheDocument();
  });

  it('toggling humanGate.enabled calls commitAuthoring after debounce', async () => {
    mockCommitAuthoring.mockClear();

    renderWithManifest(makeReadyContext(STORY_WRITER_MANIFEST));

    await waitFor(() => {
      expect(screen.getByText('Human Gate')).toBeInTheDocument();
    });

    const switches = screen.getAllByRole('switch');
    // First switch should be humanGate.enabled
    expect(switches.length).toBeGreaterThanOrEqual(1);
    const humanGateEnabledSwitch = switches[0];

    const user = userEvent.setup();
    await user.click(humanGateEnabledSwitch);

    // Wait for debounce (250ms) + buffer
    await new Promise((r) => setTimeout(r, 400));

    await waitFor(() => {
      expect(mockCommitAuthoring).toHaveBeenCalled();
    }, { timeout: 2000 });
  }, 10000);

  it('typing in a root-level field calls commitAuthoring after debounce', async () => {
    mockCommitAuthoring.mockClear();

    renderWithManifest(makeReadyContext(STORY_WRITER_MANIFEST));

    await waitFor(() => {
      expect(screen.getByDisplayValue('problem_agitation_solution')).toBeInTheDocument();
    });

    // Change the storyFormula select
    const select = screen.getByDisplayValue('problem_agitation_solution') as HTMLSelectElement;
    const user = userEvent.setup();
    await user.selectOptions(select, 'hero_journey');

    // Wait for debounce + buffer
    await new Promise((r) => setTimeout(r, 400));

    await waitFor(() => {
      expect(mockCommitAuthoring).toHaveBeenCalled();
    }, { timeout: 2000 });
  }, 10000);
});
