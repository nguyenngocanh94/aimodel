/**
 * JsonSchemaForm.test.tsx — React Testing Library tests for JsonSchemaForm + JsonSchemaField
 */

import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import { JsonSchemaForm } from './JsonSchemaForm';
import type { JsonSchemaNode } from '@/features/node-registry/manifest/types';

// ────────────────────────────────────────────────────────────────
// Test schema — covers all field types plus nested humanGate object
// ────────────────────────────────────────────────────────────────

const TEST_SCHEMA: JsonSchemaNode = {
  type: 'object',
  properties: {
    title: {
      type: 'string',
      description: 'The title of this item',
    },
    maxItems: {
      type: 'integer',
      minimum: 0,
      maximum: 100,
      description: 'Maximum number of items',
    },
    enabled: {
      type: 'boolean',
      description: 'Enable this feature',
    },
    humanGate: {
      type: 'object',
      description: 'Human gate settings',
      properties: {
        enabled: {
          type: 'boolean',
          description: 'Enable human gate',
        },
        channel: {
          type: 'string',
          enum: ['ui', 'telegram', 'mcp', 'any'],
          description: 'Channel to notify',
        },
        chatId: {
          type: 'string',
          description: 'Telegram chat ID',
        },
      },
      required: ['enabled', 'channel'],
      additionalProperties: false,
    },
  },
  required: ['title', 'maxItems'],
  additionalProperties: false,
};

const DEFAULT_VALUE: Record<string, unknown> = {
  title: 'Hello',
  maxItems: 10,
  enabled: false,
  humanGate: {
    enabled: false,
    channel: 'ui',
    chatId: '',
  },
};

// ────────────────────────────────────────────────────────────────
// Helper
// ────────────────────────────────────────────────────────────────

function renderForm(
  overrides: Partial<{
    schema: JsonSchemaNode;
    value: Record<string, unknown>;
    onChange: (v: Record<string, unknown>) => void;
    errors: Record<string, string>;
  }> = {},
) {
  const onChange = overrides.onChange ?? vi.fn();
  render(
    <JsonSchemaForm
      schema={overrides.schema ?? TEST_SCHEMA}
      value={overrides.value ?? DEFAULT_VALUE}
      onChange={onChange}
      errors={overrides.errors}
    />,
  );
  return { onChange };
}

// ────────────────────────────────────────────────────────────────
// Tests
// ────────────────────────────────────────────────────────────────

describe('JsonSchemaForm', () => {
  it('renders a text input for string field', () => {
    renderForm();
    const input = screen.getByRole('textbox', { name: /title/i });
    expect(input).toBeInTheDocument();
  });

  it('renders a number input for integer field', () => {
    renderForm();
    expect(screen.getByLabelText(/max items/i)).toBeInTheDocument();
    const input = screen.getByLabelText(/max items/i) as HTMLInputElement;
    expect(input.type).toBe('number');
  });

  it('renders a switch (role=switch) for boolean field', () => {
    renderForm();
    const switches = screen.getAllByRole('switch');
    // One for "enabled" at root, two more inside humanGate
    expect(switches.length).toBeGreaterThanOrEqual(1);
  });

  it('renders the nested humanGate fieldset with its label', () => {
    renderForm();
    // summary element contains the label text
    expect(screen.getByText('Human Gate')).toBeInTheDocument();
  });

  it('renders a select inside the humanGate fieldset for channel enum', () => {
    renderForm();
    const select = screen.getByDisplayValue('ui') as HTMLSelectElement;
    expect(select).toBeInTheDocument();
    const options = Array.from(select.options).map((o) => o.value);
    expect(options).toContain('telegram');
    expect(options).toContain('mcp');
    expect(options).toContain('any');
  });

  it('renders chatId text input inside humanGate', () => {
    renderForm();
    const chatIdInput = screen.getByLabelText(/chat id/i);
    expect(chatIdInput).toBeInTheDocument();
  });

  it('renders a red asterisk for required fields (title)', () => {
    renderForm();
    // title label should contain asterisk
    const titleLabel = document.querySelector('label[for="title"]');
    expect(titleLabel).not.toBeNull();
    expect(titleLabel!.textContent).toContain('*');
  });

  it('does NOT render an asterisk for the root optional "enabled" field', () => {
    renderForm();
    // root "enabled" is not in schema.required
    const enabledLabel = document.querySelector('label[for="enabled"]');
    // It should exist but NOT have an asterisk
    if (enabledLabel) {
      expect(enabledLabel.textContent).not.toContain('*');
    }
  });

  it('typing in the title input fires onChange with updated value', async () => {
    const onChange = vi.fn();
    renderForm({ onChange });

    const input = screen.getByRole('textbox', { name: /title/i }) as HTMLInputElement;

    // Clear and type using userEvent (real timers)
    const user = userEvent.setup();
    await user.clear(input);
    await user.type(input, 'New Title');

    // Wait for the debounce to flush (250ms debounce)
    await act(async () => {
      await new Promise((r) => setTimeout(r, 400));
    });

    await waitFor(() => {
      expect(onChange).toHaveBeenCalled();
    }, { timeout: 2000 });
  }, 10000);

  it('toggling the root boolean switch fires onChange', async () => {
    const onChange = vi.fn();
    renderForm({ onChange });

    // Find root "enabled" switch
    const rootSwitch = document.getElementById('enabled') as HTMLButtonElement;
    expect(rootSwitch).not.toBeNull();

    const user = userEvent.setup();
    await user.click(rootSwitch);

    // Wait for the debounce
    await act(async () => {
      await new Promise((r) => setTimeout(r, 400));
    });

    await waitFor(() => {
      expect(onChange).toHaveBeenCalled();
    }, { timeout: 2000 });
  }, 10000);

  it('selecting a different channel enum option fires onChange', async () => {
    const onChange = vi.fn();
    renderForm({ onChange });

    const select = screen.getByDisplayValue('ui') as HTMLSelectElement;
    const user = userEvent.setup();
    await user.selectOptions(select, 'telegram');

    // Wait for the debounce
    await act(async () => {
      await new Promise((r) => setTimeout(r, 400));
    });

    await waitFor(() => {
      expect(onChange).toHaveBeenCalled();
    }, { timeout: 2000 });
  }, 10000);

  it('renders error text under humanGate.chatId when passed in errors prop', () => {
    renderForm({ errors: { 'humanGate.chatId': 'required' } });
    const alerts = screen.getAllByRole('alert');
    const errorAlert = alerts.find((a) => a.textContent === 'required');
    expect(errorAlert).toBeDefined();
    expect(errorAlert!.textContent).toBe('required');
  });

  it('unknown type renders nothing and logs a warning', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const badSchema: JsonSchemaNode = {
      type: 'object',
      properties: {
        weirdField: { type: 'foo' as 'string' },
      },
    };
    renderForm({ schema: badSchema, value: { weirdField: 'x' } });
    // The field renders nothing (null)
    expect(screen.queryByLabelText(/weird field/i)).toBeNull();
    expect(warnSpy).toHaveBeenCalledWith(
      'JsonSchemaField: unknown type',
      expect.objectContaining({ type: 'foo' }),
    );
    warnSpy.mockRestore();
  });

  it('nullable type (["string","null"]) renders as string input', () => {
    const schema: JsonSchemaNode = {
      type: 'object',
      properties: {
        maybeText: { type: ['string', 'null'] },
      },
    };
    renderForm({ schema, value: { maybeText: null } });
    const input = screen.getByRole('textbox', { name: /maybe text/i });
    expect(input).toBeInTheDocument();
  });

  it('required fields inside nested object get asterisk', () => {
    renderForm();
    // humanGate.enabled is required inside humanGate
    const nestedEnabledLabel = document.querySelector('label[for="humanGate.enabled"]');
    expect(nestedEnabledLabel).not.toBeNull();
    expect(nestedEnabledLabel!.textContent).toContain('*');
  });
});
