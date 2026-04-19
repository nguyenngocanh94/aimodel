/**
 * Manifest types — mirrors the NM1 backend response shape exactly.
 * All interfaces are readonly to enforce immutability per AGENTS.md rule 4.
 */

export interface PortManifest {
  readonly key: string;
  readonly label: string;
  readonly dataType: string;
  readonly direction: 'input' | 'output';
  readonly required: boolean;
  readonly multiple: boolean;
  readonly description?: string;
}

export interface JsonSchemaNode {
  readonly type?: string | readonly string[]; // e.g. ["string","null"] for nullable
  readonly properties?: Readonly<Record<string, JsonSchemaNode>>;
  readonly required?: readonly string[];
  readonly enum?: readonly (string | number | boolean)[];
  readonly minLength?: number;
  readonly maxLength?: number;
  readonly minimum?: number;
  readonly maximum?: number;
  readonly default?: unknown;
  readonly description?: string;
  readonly items?: JsonSchemaNode; // for array types
  readonly additionalProperties?: boolean;
  readonly $schema?: string;
}

export interface NodeManifest {
  readonly type: string;
  readonly version: string;
  readonly title: string;
  readonly description: string;
  readonly category: string;
  readonly ports: {
    readonly inputs: readonly PortManifest[];
    readonly outputs: readonly PortManifest[];
  };
  readonly configSchema: JsonSchemaNode;
  readonly defaultConfig: Readonly<Record<string, unknown>>;
  readonly humanGateEnabled: boolean;
  readonly executable: boolean;
}

export interface ManifestResponse {
  readonly version: string; // sha256-hex (64 chars) — ideal localStorage cache key
  readonly nodes: Readonly<Record<string, NodeManifest>>;
}
