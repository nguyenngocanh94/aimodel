/**
 * JsonSchemaField — recursive JSON-Schema-driven form field.
 *
 * Rendered by JsonSchemaForm for each property in the schema.
 * Supports: string, integer, number, boolean, array, object, enum, nullable.
 *
 * Type resolution: if schema.type is an array (e.g. ["string","null"]),
 * pick the first non-"null" entry as the base type and set nullable=true.
 * Helper exported as resolveBaseType (reusable by NM3+).
 */

import React, { useCallback } from 'react';
import { useFormContext, useFieldArray, Controller } from 'react-hook-form';
import type { JsonSchemaNode } from '@/features/node-registry/manifest/types';
import { cn } from '@/shared/lib/utils';

// ────────────────────────────────────────────────────────────────
// Type resolution helper (exported for NM3+ reuse)
// ────────────────────────────────────────────────────────────────

export interface ResolvedType {
  baseType: string;
  nullable: boolean;
}

/**
 * resolveBaseType — given a JsonSchemaNode, returns the effective base type
 * and whether the field is nullable.
 *
 * Handles:
 *   schema.type = "string"               → { baseType: "string", nullable: false }
 *   schema.type = ["string", "null"]     → { baseType: "string", nullable: true }
 *   schema.type = undefined, has enum    → { baseType: "string", nullable: false }
 *   schema.type = undefined              → { baseType: "unknown", nullable: false }
 */
export function resolveBaseType(schema: JsonSchemaNode): ResolvedType {
  const { type } = schema;

  if (type === undefined) {
    // If there's an enum with no type, treat as string
    if (schema.enum !== undefined) {
      return { baseType: 'string', nullable: false };
    }
    // If there are properties, treat as object
    if (schema.properties !== undefined) {
      return { baseType: 'object', nullable: false };
    }
    return { baseType: 'unknown', nullable: false };
  }

  if (typeof type === 'string') {
    return { baseType: type, nullable: false };
  }

  // Array of types — find first non-null
  const nonNull = (type as readonly string[]).filter((t) => t !== 'null');
  const nullable = (type as readonly string[]).includes('null');
  const baseType = nonNull[0] ?? 'unknown';
  return { baseType, nullable };
}

// ────────────────────────────────────────────────────────────────
// Label humanizer
// ────────────────────────────────────────────────────────────────

function humanizeKey(key: string): string {
  // "humanGateChannel" → "Human Gate Channel"
  // "humanGate.channel" → "Channel" (last segment only for nested)
  const segment = key.includes('.') ? (key.split('.').pop() ?? key) : key;
  return segment
    .replace(/([A-Z])/g, ' $1')
    .replace(/^./, (s) => s.toUpperCase())
    .trim();
}

function humanizeTopKey(key: string): string {
  // For top-level labels, show full humanized name without dot splitting
  return key
    .replace(/([A-Z])/g, ' $1')
    .replace(/^./, (s) => s.toUpperCase())
    .trim();
}

// ────────────────────────────────────────────────────────────────
// Shared field wrapper
// ────────────────────────────────────────────────────────────────

interface FieldWrapperProps {
  readonly path: string;
  readonly label: string;
  readonly required: boolean;
  readonly description?: string;
  readonly errors?: Readonly<Record<string, string>>;
  readonly children: React.ReactNode;
}

function FieldWrapper({
  path,
  label,
  required,
  description,
  errors,
  children,
}: FieldWrapperProps) {
  const errorMsg = errors?.[path];
  return (
    <div className="space-y-1">
      <label
        htmlFor={path}
        className="block text-[11px] font-medium text-muted-foreground"
      >
        {label}
        {required && (
          <span className="ml-0.5 text-destructive" aria-label="required">
            *
          </span>
        )}
      </label>
      {children}
      {description && (
        <p className="text-xs text-muted-foreground">{description}</p>
      )}
      {errorMsg && (
        <p className="text-xs text-destructive" role="alert">
          {errorMsg}
        </p>
      )}
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// Array field — simple repeater
// ────────────────────────────────────────────────────────────────

interface ArrayFieldProps {
  readonly path: string;
  readonly schema: JsonSchemaNode;
  readonly label: string;
  readonly required: boolean;
  readonly errors?: Readonly<Record<string, string>>;
}

function ArrayField({ path, schema, label, required, errors }: ArrayFieldProps) {
  const { control } = useFormContext();
  const { fields, append, remove } = useFieldArray({ control, name: path });

  const itemsSchema = schema.items;

  const handleAdd = useCallback(() => {
    if (!itemsSchema) {
      append('');
      return;
    }
    const { baseType } = resolveBaseType(itemsSchema);
    switch (baseType) {
      case 'boolean':
        append(false);
        break;
      case 'integer':
      case 'number':
        append(0);
        break;
      default:
        append('');
    }
  }, [append, itemsSchema]);

  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between">
        <label className="block text-[11px] font-medium text-muted-foreground">
          {label}
          {required && (
            <span className="ml-0.5 text-destructive" aria-label="required">
              *
            </span>
          )}
        </label>
        <button
          type="button"
          onClick={handleAdd}
          className="text-[10px] text-primary hover:text-primary/80 font-medium"
          aria-label={`Add ${label} item`}
        >
          + Add
        </button>
      </div>
      {fields.map((field, index) => (
        <div key={field.id} className="flex items-center gap-1">
          <div className="flex-1">
            {itemsSchema ? (
              <JsonSchemaField
                path={`${path}.${index}`}
                schema={itemsSchema}
                required={false}
                errors={errors}
                hideLabel
              />
            ) : (
              <StringInput path={`${path}.${index}`} />
            )}
          </div>
          <button
            type="button"
            onClick={() => remove(index)}
            className="text-[10px] text-muted-foreground hover:text-destructive"
            aria-label={`Remove item ${index + 1}`}
          >
            ✕
          </button>
        </div>
      ))}
      {errors?.[path] && (
        <p className="text-xs text-destructive" role="alert">
          {errors[path]}
        </p>
      )}
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// Primitive inputs (controlled via React Hook Form Controller)
// ────────────────────────────────────────────────────────────────

interface StringInputProps {
  readonly path: string;
  readonly schema?: JsonSchemaNode;
  readonly placeholder?: string;
}

function StringInput({ path, schema, placeholder }: StringInputProps) {
  const { control } = useFormContext();
  const maxLength = schema?.maxLength;
  const isTextarea = maxLength !== undefined && maxLength > 200;

  return (
    <Controller
      name={path}
      control={control}
      render={({ field }) => {
        const strVal = field.value === null || field.value === undefined ? '' : String(field.value);
        if (isTextarea) {
          return (
            <textarea
              {...field}
              id={path}
              value={strVal}
              rows={3}
              placeholder={placeholder}
              className="w-full rounded-md border border-input bg-muted px-2 py-1 text-sm resize-y focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
          );
        }
        return (
          <input
            {...field}
            id={path}
            value={strVal}
            type="text"
            placeholder={placeholder}
            className="h-8 w-full rounded-md border border-input bg-muted px-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
        );
      }}
    />
  );
}

interface EnumSelectProps {
  readonly path: string;
  readonly options: readonly (string | number | boolean)[];
}

function EnumSelect({ path, options }: EnumSelectProps) {
  const { control } = useFormContext();
  return (
    <Controller
      name={path}
      control={control}
      render={({ field }) => (
        <select
          {...field}
          id={path}
          value={field.value === null || field.value === undefined ? '' : String(field.value)}
          onChange={(e) => field.onChange(e.target.value)}
          className="h-8 w-full rounded-md border border-input bg-muted px-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          {options.map((opt) => (
            <option key={String(opt)} value={String(opt)}>
              {String(opt)}
            </option>
          ))}
        </select>
      )}
    />
  );
}

interface NumberInputProps {
  readonly path: string;
  readonly schema: JsonSchemaNode;
  readonly isInteger: boolean;
}

function NumberInput({ path, schema, isInteger }: NumberInputProps) {
  const { control } = useFormContext();
  return (
    <Controller
      name={path}
      control={control}
      render={({ field }) => (
        <input
          id={path}
          type="number"
          min={schema.minimum}
          max={schema.maximum}
          step={isInteger ? 1 : 0.01}
          value={field.value === null || field.value === undefined ? '' : String(field.value)}
          onChange={(e) => {
            const val = e.target.value;
            if (val === '') {
              field.onChange(null);
            } else {
              field.onChange(isInteger ? parseInt(val, 10) : parseFloat(val));
            }
          }}
          onBlur={field.onBlur}
          name={field.name}
          className="h-8 w-full rounded-md border border-input bg-muted px-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
      )}
    />
  );
}

interface BooleanSwitchProps {
  readonly path: string;
}

function BooleanSwitch({ path }: BooleanSwitchProps) {
  const { control } = useFormContext();
  return (
    <Controller
      name={path}
      control={control}
      render={({ field }) => {
        const checked = Boolean(field.value);
        return (
          <button
            type="button"
            role="switch"
            id={path}
            aria-checked={checked}
            onClick={() => field.onChange(!checked)}
            className={cn(
              'relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              checked ? 'bg-primary' : 'bg-input',
            )}
          >
            <span
              className={cn(
                'inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform',
                checked ? 'translate-x-4' : 'translate-x-0.5',
              )}
            />
          </button>
        );
      }}
    />
  );
}

// ────────────────────────────────────────────────────────────────
// Object field — collapsible fieldset
// ────────────────────────────────────────────────────────────────

interface ObjectFieldProps {
  readonly path: string;
  readonly schema: JsonSchemaNode;
  readonly label: string;
  readonly required: boolean;
  readonly errors?: Readonly<Record<string, string>>;
}

function ObjectField({ path, schema, label, required, errors }: ObjectFieldProps) {
  const properties = schema.properties ?? {};

  return (
    <details
      className="rounded-md border border-border"
      open
    >
      <summary className="cursor-pointer select-none px-3 py-2 text-[11px] font-medium text-muted-foreground hover:bg-muted/50">
        {label}
        {required && (
          <span className="ml-0.5 text-destructive" aria-label="required">
            *
          </span>
        )}
      </summary>
      <div className="space-y-3 px-3 pb-3 pt-1">
        {Object.entries(properties).map(([key, propSchema]) => {
          const childPath = `${path}.${key}`;
          const isRequired = schema.required?.includes(key) ?? false;
          return (
            <JsonSchemaField
              key={childPath}
              path={childPath}
              schema={propSchema}
              required={isRequired}
              errors={errors}
            />
          );
        })}
      </div>
    </details>
  );
}

// ────────────────────────────────────────────────────────────────
// JsonSchemaField — main recursive component
// ────────────────────────────────────────────────────────────────

interface JsonSchemaFieldProps {
  readonly path: string;
  readonly schema: JsonSchemaNode;
  readonly required: boolean;
  readonly errors?: Readonly<Record<string, string>>;
  /** When true, renders the widget without a label wrapper (used in array items) */
  readonly hideLabel?: boolean;
}

export function JsonSchemaField({
  path,
  schema,
  required,
  errors,
  hideLabel = false,
}: JsonSchemaFieldProps) {
  const { baseType } = resolveBaseType(schema);
  const rawLabel = path.includes('.')
    ? humanizeKey(path)
    : humanizeTopKey(path);
  const label = rawLabel;

  // ── string ──────────────────────────────────────────────────
  if (baseType === 'string') {
    if (schema.enum && schema.enum.length > 0) {
      const widget = <EnumSelect path={path} options={schema.enum} />;
      if (hideLabel) return widget;
      return (
        <FieldWrapper
          path={path}
          label={label}
          required={required}
          description={schema.description}
          errors={errors}
        >
          {widget}
        </FieldWrapper>
      );
    }
    const placeholder =
      schema.default !== undefined ? String(schema.default) : undefined;
    const widget = (
      <StringInput path={path} schema={schema} placeholder={placeholder} />
    );
    if (hideLabel) return widget;
    return (
      <FieldWrapper
        path={path}
        label={label}
        required={required}
        description={schema.description}
        errors={errors}
      >
        {widget}
      </FieldWrapper>
    );
  }

  // ── integer / number ─────────────────────────────────────────
  if (baseType === 'integer' || baseType === 'number') {
    const widget = (
      <NumberInput path={path} schema={schema} isInteger={baseType === 'integer'} />
    );
    if (hideLabel) return widget;
    return (
      <FieldWrapper
        path={path}
        label={label}
        required={required}
        description={schema.description}
        errors={errors}
      >
        {widget}
      </FieldWrapper>
    );
  }

  // ── boolean ──────────────────────────────────────────────────
  if (baseType === 'boolean') {
    const widget = <BooleanSwitch path={path} />;
    if (hideLabel) return widget;
    return (
      <FieldWrapper
        path={path}
        label={label}
        required={required}
        description={schema.description}
        errors={errors}
      >
        {widget}
      </FieldWrapper>
    );
  }

  // ── array ────────────────────────────────────────────────────
  if (baseType === 'array') {
    return (
      <ArrayField
        path={path}
        schema={schema}
        label={hideLabel ? '' : label}
        required={required}
        errors={errors}
      />
    );
  }

  // ── object ───────────────────────────────────────────────────
  if (baseType === 'object') {
    return (
      <ObjectField
        path={path}
        schema={schema}
        label={label}
        required={required}
        errors={errors}
      />
    );
  }

  // ── unknown ──────────────────────────────────────────────────
  console.warn('JsonSchemaField: unknown type', schema);
  return null;
}
