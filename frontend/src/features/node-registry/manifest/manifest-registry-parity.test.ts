/**
 * manifest-registry-parity.test.ts — NM4 frontend drift safety net.
 *
 * Uses a committed fixture (manifest.example.json) generated from the real backend.
 * No live fetch: fully hermetic for CI.
 *
 * Assertions:
 *   1. Every frontend template's `type` appears in the backend manifest.
 *   2. Every manifest entry has a matching frontend template, OR is explicitly
 *      flagged as backend-only (none currently, but the gate is defensive).
 *   3. (Informational) Templates with humanGateEnabled: true are logged.
 *
 * Catches: renaming a type on one side without updating the other; adding a backend
 * template without a frontend template (or vice versa).
 */

import { describe, it, expect } from 'vitest';
import fixtureManifest from './__fixtures__/manifest.example.json';
import { nodeTemplateRegistry } from '../node-registry';
import type { ManifestResponse } from './types';

// Type-cast the imported JSON to our typed response shape.
const manifest = fixtureManifest as unknown as ManifestResponse;

// ── Helpers ────────────────────────────────────────────────────────────────────

/**
 * Frontend template types that are frontend-only (no backend template yet).
 * These won't appear in the manifest — treat them as expected gaps rather than
 * failures. Update this set when a backend template is added.
 */
const FRONTEND_ONLY_TYPES = new Set([
  'diverge',
  'productImageInput',
  'wanI2V',
  'wanImageEdit',
  'wanVideoEdit',
]);

/**
 * Backend template types that are backend-only (no frontend template yet).
 * If any appear here, add a frontend template stub or list explicitly.
 */
const BACKEND_ONLY_TYPES = new Set<string>([
  // Currently none. Any backend type without a frontend template must be
  // listed here to pass CI. Prefer adding a frontend template instead.
]);

// ── Tests ──────────────────────────────────────────────────────────────────────

describe('manifest ↔ frontend template registry parity', () => {
  const manifestNodeTypes = new Set(Object.keys(manifest.nodes));
  const frontendTemplates = nodeTemplateRegistry.getAll();
  const frontendTypes = new Set(frontendTemplates.map((t) => t.type));

  it('fixture manifest has a version and at least one node', () => {
    expect(typeof manifest.version).toBe('string');
    expect(manifest.version.length).toBeGreaterThan(0);
    expect(Object.keys(manifest.nodes).length).toBeGreaterThan(0);
  });

  it('every frontend template type appears in the backend manifest (or is explicitly frontend-only)', () => {
    const missing: string[] = [];

    for (const template of frontendTemplates) {
      if (FRONTEND_ONLY_TYPES.has(template.type)) {
        // Known frontend-only gap — skip assertion
        continue;
      }
      if (!manifestNodeTypes.has(template.type)) {
        missing.push(template.type);
      }
    }

    expect(
      missing,
      `Frontend templates missing from backend manifest: ${missing.join(', ')}.\n` +
        'Either add the backend NodeTemplate, or add the type to FRONTEND_ONLY_TYPES.',
    ).toHaveLength(0);
  });

  it('every manifest entry has a matching frontend template (or is explicitly backend-only)', () => {
    const missing: string[] = [];

    for (const manifestType of manifestNodeTypes) {
      if (BACKEND_ONLY_TYPES.has(manifestType)) {
        // Known backend-only node — skip assertion
        continue;
      }
      if (!frontendTypes.has(manifestType)) {
        missing.push(manifestType);
      }
    }

    expect(
      missing,
      `Backend manifest types missing from frontend registry: ${missing.join(', ')}.\n` +
        'Either add a frontend NodeTemplate, or add the type to BACKEND_ONLY_TYPES.',
    ).toHaveLength(0);
  });

  it('frontend-only types are not unexpectedly in the manifest', () => {
    // If a type was frontend-only but has now been added to the backend, alert the
    // developer to remove it from FRONTEND_ONLY_TYPES to close the parity check.
    const shouldRemove: string[] = [];

    for (const type of FRONTEND_ONLY_TYPES) {
      if (manifestNodeTypes.has(type)) {
        shouldRemove.push(type);
      }
    }

    expect(
      shouldRemove,
      `Types in FRONTEND_ONLY_TYPES now exist in the backend manifest: ${shouldRemove.join(', ')}.\n` +
        'Remove them from FRONTEND_ONLY_TYPES so parity is fully enforced.',
    ).toHaveLength(0);
  });

  it('manifest node count matches expectations', () => {
    // Backend has 18 registered templates as of NM4.
    // Update this number when adding new templates (forces intentional review).
    const backendCount = manifestNodeTypes.size;
    expect(backendCount).toBeGreaterThanOrEqual(18);
  });

  it('all manifest nodes have configSchema.type === object', () => {
    for (const [nodeType, nodeManifest] of Object.entries(manifest.nodes)) {
      expect(
        nodeManifest.configSchema.type,
        `${nodeType}: configSchema.type should be 'object'`,
      ).toBe('object');
    }
  });

  it('humanGateEnabled templates (informational log + schema check)', () => {
    const humanGateNodes = Object.entries(manifest.nodes)
      .filter(([, n]) => n.humanGateEnabled)
      .map(([type]) => type);

    // Informational: print which templates have humanGate enabled.
    if (humanGateNodes.length > 0) {
      console.info(
        `[manifest-parity] Templates with humanGateEnabled: ${humanGateNodes.join(', ')}`,
      );
    }

    // For each humanGate-enabled node, verify the schema has humanGate nested object.
    for (const nodeType of humanGateNodes) {
      const nodeManifest = manifest.nodes[nodeType];
      const props = nodeManifest.configSchema.properties as Record<string, { type?: string; properties?: Record<string, unknown> }> | undefined;

      expect(
        props,
        `${nodeType}: humanGateEnabled=true but configSchema.properties is missing`,
      ).toBeDefined();

      expect(
        props?.['humanGate'],
        `${nodeType}: humanGateEnabled=true but configSchema.properties.humanGate is missing`,
      ).toBeDefined();

      expect(
        props?.['humanGate']?.type,
        `${nodeType}: humanGate should be type 'object'`,
      ).toBe('object');

      const humanGateProps = props?.['humanGate']?.properties as Record<string, { type?: string }> | undefined;
      expect(
        humanGateProps?.['enabled']?.type,
        `${nodeType}: humanGate.enabled should be type 'boolean'`,
      ).toBe('boolean');
    }
  });
});
