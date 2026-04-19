/**
 * JsonSchemaForm — top-level form that renders all properties
 * from a JSON Schema "object" node using JsonSchemaField.
 *
 * Uses React Hook Form (useForm) for internal state.
 * Calls onChange (debounced 250 ms) on every value change.
 * Controlled externally via the value prop (reset on prop change).
 */

import { useEffect, useRef, useCallback } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import type { JsonSchemaNode } from '@/features/node-registry/manifest/types';
import { JsonSchemaField } from './JsonSchemaField';

// ────────────────────────────────────────────────────────────────
// Props
// ────────────────────────────────────────────────────────────────

export interface JsonSchemaFormProps {
  readonly schema: JsonSchemaNode; // must be type: "object" at root
  readonly value: Record<string, unknown>;
  readonly onChange: (next: Record<string, unknown>) => void;
  readonly errors?: Readonly<Record<string, string>>; // paths like "humanGate.chatId"
}

// ────────────────────────────────────────────────────────────────
// Debounce helper
// ────────────────────────────────────────────────────────────────

function useDebounced<T extends (...args: Parameters<T>) => void>(
  fn: T,
  delayMs: number,
): (...args: Parameters<T>) => void {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const fnRef = useRef(fn);
  fnRef.current = fn;

  const debounced = useCallback(
    (...args: Parameters<T>) => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
      }
      timerRef.current = setTimeout(() => {
        fnRef.current(...args);
      }, delayMs);
    },
    [delayMs],
  );

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
      }
    };
  }, []);

  return debounced;
}

// ────────────────────────────────────────────────────────────────
// JsonSchemaForm
// ────────────────────────────────────────────────────────────────

export function JsonSchemaForm({
  schema,
  value,
  onChange,
  errors,
}: JsonSchemaFormProps) {
  const methods = useForm<Record<string, unknown>>({
    defaultValues: value,
    mode: 'onChange',
  });

  const { watch, reset } = methods;

  // Reset form when external value changes (e.g. node selection change)
  const valueRef = useRef(value);
  useEffect(() => {
    // Only reset if the reference changed (avoid infinite loop)
    if (valueRef.current !== value) {
      valueRef.current = value;
      reset(value);
    }
  }, [value, reset]);

  const debouncedOnChange = useDebounced(onChange, 250);

  // Subscribe to form changes and emit via debounced onChange
  useEffect(() => {
    const { unsubscribe } = watch((formValues) => {
      debouncedOnChange(formValues as Record<string, unknown>);
    });
    return unsubscribe;
  }, [watch, debouncedOnChange]);

  const properties = schema.properties ?? {};
  const requiredFields = schema.required ?? [];

  return (
    <FormProvider {...methods}>
      <form className="space-y-3" onSubmit={(e) => e.preventDefault()}>
        {Object.entries(properties).map(([key, propSchema]) => (
          <JsonSchemaField
            key={key}
            path={key}
            schema={propSchema}
            required={requiredFields.includes(key)}
            errors={errors}
          />
        ))}
      </form>
    </FormProvider>
  );
}
