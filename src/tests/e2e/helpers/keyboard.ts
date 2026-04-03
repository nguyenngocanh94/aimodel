import type { Page } from '@playwright/test';

const isMac = process.platform === 'darwin';
const modifier = isMac ? 'Meta' : 'Control';

type ShortcutMap = {
  'save': string;
  'undo': string;
  'redo': string;
  'export': string;
  'run': string;
  'delete': string;
  'selectAll': string;
  'escape': string;
};

const shortcuts: ShortcutMap = {
  'save': `${modifier}+KeyS`,
  'undo': `${modifier}+KeyZ`,
  'redo': `${modifier}+Shift+KeyZ`,
  'export': `${modifier}+Shift+KeyE`,
  'run': 'Shift+KeyR',
  'delete': 'Delete',
  'selectAll': `${modifier}+KeyA`,
  'escape': 'Escape',
};

type ShortcutName = keyof ShortcutMap;

export async function pressShortcut(
  page: Page,
  shortcut: ShortcutName
): Promise<void> {
  const keyCombo = shortcuts[shortcut];
  
  if (!keyCombo) {
    throw new Error(`Unknown shortcut: ${shortcut}`);
  }

  const keys = keyCombo.split('+');
  
  if (keys.length === 1) {
    await page.keyboard.press(keys[0]!);
  } else {
    await page.keyboard.press(keyCombo);
  }
}

export function getModifierKey(): string {
  return modifier;
}

export function isMacPlatform(): boolean {
  return isMac;
}
