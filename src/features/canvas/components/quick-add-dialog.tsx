import { useCallback, useEffect, useRef, useState } from 'react';
import { getTemplateMetadata } from '@/features/node-registry/node-registry';
import { cn } from '@/shared/lib/utils';

interface QuickAddDialogProps {
  readonly open: boolean;
  readonly onClose: () => void;
  readonly onSelect: (templateType: string) => void;
}

/**
 * QuickAddDialog — Searchable command menu for adding nodes via 'A' shortcut.
 * Design system section 16: Quick-add node command menu.
 */
export function QuickAddDialog({ open, onClose, onSelect }: QuickAddDialogProps) {
  const [query, setQuery] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  const templates = getTemplateMetadata();
  const filtered = query
    ? templates.filter(
        (t) =>
          t.title.toLowerCase().includes(query.toLowerCase()) ||
          t.category.toLowerCase().includes(query.toLowerCase()),
      )
    : templates;

  useEffect(() => {
    if (open) {
      setQuery('');
      // Focus input after dialog opens
      requestAnimationFrame(() => inputRef.current?.focus());
    }
  }, [open]);

  const handleSelect = useCallback(
    (type: string) => {
      onSelect(type);
      onClose();
    },
    [onSelect, onClose],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    },
    [onClose],
  );

  if (!open) return null;

  return (
    <div
      data-testid="quick-add-dialog"
      data-state="open"
      role="dialog"
      aria-label="Quick add node"
      className="fixed inset-0 z-[var(--z-dialog)] flex items-start justify-center pt-[20vh]"
      onClick={onClose}
      onKeyDown={handleKeyDown}
    >
      <div
        className="w-80 rounded-lg border border-border bg-card shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="border-b border-border p-3">
          <input
            ref={inputRef}
            type="text"
            placeholder="Search nodes..."
            aria-label="Search nodes to add"
            className="w-full bg-transparent text-sm text-foreground placeholder:text-muted-foreground outline-none"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
        <div className="max-h-64 overflow-y-auto p-1">
          {filtered.length === 0 && (
            <div className="px-3 py-6 text-center text-sm text-muted-foreground">
              No matching nodes
            </div>
          )}
          {filtered.map((t) => (
            <button
              key={t.type}
              type="button"
              className={cn(
                'flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm',
                'text-foreground hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                'transition-hover',
              )}
              onClick={() => handleSelect(t.type)}
            >
              <span className="font-medium">{t.title}</span>
              <span className="text-xs text-muted-foreground">{t.category}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
