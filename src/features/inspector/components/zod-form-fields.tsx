import { type UseFormRegister, type FieldErrors } from 'react-hook-form';
import { z } from 'zod';

interface ZodFormFieldsProps {
  readonly schema: z.ZodType;
  readonly register: UseFormRegister<Record<string, unknown>>;
  readonly errors: FieldErrors;
  readonly disabled?: boolean;
}

interface FieldDef {
  readonly name: string;
  readonly label: string;
  readonly type: 'text' | 'number' | 'select' | 'checkbox' | 'textarea';
  readonly description?: string;
  readonly options?: readonly string[];
  readonly min?: number;
  readonly max?: number;
}

/**
 * Extract form field definitions from a Zod object schema.
 * Handles: z.string(), z.number(), z.boolean(), z.enum(), z.literal().
 */
function extractFieldDefs(schema: z.ZodType): FieldDef[] {
  const fields: FieldDef[] = [];

  // Unwrap ZodEffects and similar wrappers
  let inner = schema;
  if (inner instanceof z.ZodEffects) {
    inner = inner._def.schema;
  }

  if (!(inner instanceof z.ZodObject)) return fields;

  const shape = inner.shape;
  for (const [name, fieldSchema] of Object.entries(shape)) {
    const field = parseField(name, fieldSchema as z.ZodType);
    if (field) fields.push(field);
  }

  return fields;
}

function parseField(name: string, schema: z.ZodType): FieldDef | null {
  // Unwrap optional/default/describe
  let inner = schema;
  let description: string | undefined;

  while (true) {
    if (inner instanceof z.ZodOptional) {
      inner = inner._def.innerType;
    } else if (inner instanceof z.ZodDefault) {
      inner = inner._def.innerType;
    } else if (inner._def?.description) {
      description = inner._def.description;
      break;
    } else {
      break;
    }
  }

  description = description ?? inner._def?.description;
  const label = name.replace(/([A-Z])/g, ' $1').replace(/^./, (s) => s.toUpperCase());

  if (inner instanceof z.ZodString) {
    const maxLength = inner._def.checks?.find(
      (c: { kind: string }) => c.kind === 'max',
    ) as { value: number } | undefined;
    return {
      name,
      label,
      type: maxLength && maxLength.value > 200 ? 'textarea' : 'text',
      description,
    };
  }

  if (inner instanceof z.ZodNumber) {
    const minCheck = inner._def.checks?.find(
      (c: { kind: string }) => c.kind === 'min',
    ) as { value: number } | undefined;
    const maxCheck = inner._def.checks?.find(
      (c: { kind: string }) => c.kind === 'max',
    ) as { value: number } | undefined;
    return {
      name,
      label,
      type: 'number',
      description,
      min: minCheck?.value,
      max: maxCheck?.value,
    };
  }

  if (inner instanceof z.ZodBoolean) {
    return { name, label, type: 'checkbox', description };
  }

  if (inner instanceof z.ZodEnum) {
    const options = inner._def.values as readonly string[];
    return { name, label, type: 'select', description, options };
  }

  if (inner instanceof z.ZodLiteral) {
    return { name, label, type: 'text', description };
  }

  // Fallback: render as text
  return { name, label, type: 'text', description };
}

/**
 * ZodFormFields - Renders form fields dynamically from a Zod schema
 */
export function ZodFormFields({
  schema,
  register,
  errors,
  disabled = false,
}: ZodFormFieldsProps) {
  const fields = extractFieldDefs(schema);

  if (fields.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        No configurable fields for this node type.
      </p>
    );
  }

  return (
    <div className="space-y-3">
      {fields.map((field) => {
        const error = errors[field.name];
        return (
          <div key={field.name}>
            <label
              htmlFor={field.name}
              className="block text-[11px] font-medium text-muted-foreground mb-1"
            >
              {field.label}
            </label>

            {field.type === 'text' && (
              <input
                id={field.name}
                type="text"
                disabled={disabled}
                aria-invalid={!!error}
                aria-describedby={error ? `${field.name}-error` : field.description ? `${field.name}-desc` : undefined}
                {...register(field.name)}
                className="h-8 w-full rounded-md border border-input bg-muted px-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50"
              />
            )}

            {field.type === 'textarea' && (
              <textarea
                id={field.name}
                disabled={disabled}
                rows={3}
                aria-invalid={!!error}
                aria-describedby={error ? `${field.name}-error` : field.description ? `${field.name}-desc` : undefined}
                {...register(field.name)}
                className="w-full rounded-md border border-input bg-muted px-2 py-1 text-sm resize-y focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50"
              />
            )}

            {field.type === 'number' && (
              <input
                id={field.name}
                type="number"
                disabled={disabled}
                min={field.min}
                max={field.max}
                aria-invalid={!!error}
                aria-describedby={error ? `${field.name}-error` : field.description ? `${field.name}-desc` : undefined}
                {...register(field.name, { valueAsNumber: true })}
                className="h-8 w-full rounded-md border border-input bg-muted px-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50"
              />
            )}

            {field.type === 'select' && field.options && (
              <select
                id={field.name}
                disabled={disabled}
                aria-invalid={!!error}
                aria-describedby={error ? `${field.name}-error` : field.description ? `${field.name}-desc` : undefined}
                {...register(field.name)}
                className="h-8 w-full rounded-md border border-input bg-muted px-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50"
              >
                {field.options.map((opt) => (
                  <option key={opt} value={opt}>
                    {opt}
                  </option>
                ))}
              </select>
            )}

            {field.type === 'checkbox' && (
              <div className="flex items-center gap-2">
                <input
                  id={field.name}
                  type="checkbox"
                  disabled={disabled}
                  aria-invalid={!!error}
                  aria-describedby={error ? `${field.name}-error` : field.description ? `${field.name}-desc` : undefined}
                  {...register(field.name)}
                  className="h-4 w-4 rounded border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                />
                {field.description && (
                  <span className="text-xs text-muted-foreground">
                    {field.description}
                  </span>
                )}
              </div>
            )}

            {field.type !== 'checkbox' && field.description && (
              <p id={`${field.name}-desc`} className="text-[10px] text-muted-foreground mt-0.5">
                {field.description}
              </p>
            )}

            {error && (
              <p id={`${field.name}-error`} role="alert" className="text-[10px] text-destructive mt-0.5">
                {String(error.message ?? 'Invalid')}
              </p>
            )}
          </div>
        );
      })}
    </div>
  );
}
