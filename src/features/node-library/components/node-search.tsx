import { useEffect, useRef, useState } from 'react';
import { Search, X } from 'lucide-react';
import { cn } from '@/shared/lib/utils';

interface NodeSearchProps {
  readonly value: string;
  readonly onChange: (value: string) => void;
  readonly debounceMs?: number;
  readonly disabled?: boolean;
}

/**
 * NodeSearch — Sticky search input with light debounce.
 * Design system section 9: search filters immediately with light debounce.
 */
export function NodeSearch({
  value,
  onChange,
  debounceMs = 150,
  disabled = false,
}: NodeSearchProps) {
  const [localValue, setLocalValue] = useState(value);
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const inputRef = useRef<HTMLInputElement>(null);

  // Sync external value changes
  useEffect(() => {
    setLocalValue(value);
  }, [value]);

  const handleChange = (nextValue: string) => {
    setLocalValue(nextValue);
    clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => {
      onChange(nextValue);
    }, debounceMs);
  };

  // Cleanup timer on unmount
  useEffect(() => {
    return () => clearTimeout(timerRef.current);
  }, []);

  const handleClear = () => {
    setLocalValue('');
    onChange('');
    inputRef.current?.focus();
  };

  return (
    <div className="sticky top-0 z-10 border-b border-border bg-card/95 px-3 py-2 backdrop-blur">
      <div className="relative">
        <Search
          className="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <input
          ref={inputRef}
          type="text"
          placeholder="Search nodes..."
          aria-label="Search nodes"
          data-testid="node-search-input"
          disabled={disabled}
          value={localValue}
          onChange={(e) => handleChange(e.target.value)}
          className={cn(
            'h-8 w-full rounded-md border border-input bg-background pl-8 pr-8 text-sm',
            'placeholder:text-muted-foreground',
            'focus:outline-none focus:ring-1 focus:ring-ring',
            'transition-hover',
            disabled && 'cursor-not-allowed opacity-50',
          )}
        />
        {localValue && (
          <button
            type="button"
            onClick={handleClear}
            className="absolute right-2 top-1/2 -translate-y-1/2 rounded-sm p-0.5 text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            aria-label="Clear search"
          >
            <X className="h-3 w-3" />
          </button>
        )}
      </div>
    </div>
  );
}
