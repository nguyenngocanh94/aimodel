import { useCallback, useMemo, useRef, useState } from 'react';
import { ChevronDown, ChevronRight, LayoutGrid, List, SearchX, Keyboard } from 'lucide-react';
import { Panel, PanelContent, PanelHeader, PanelTitle } from '@/shared/ui/panel';
import { Button } from '@/shared/ui/button';
import { Skeleton } from '@/shared/ui/skeleton';
import { getTemplateMetadata, type TemplateMetadata } from '@/features/node-registry/node-registry';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { NodeLibraryItem } from './node-library-item';
import { NodeSearch } from './node-search';

const categoryOrder: readonly string[] = [
  'input',
  'script',
  'visuals',
  'audio',
  'video',
  'utility',
  'output',
];

const categoryLabels: Record<string, string> = {
  input: 'Input',
  script: 'Script',
  visuals: 'Visuals',
  audio: 'Audio',
  video: 'Video',
  utility: 'Utility',
  output: 'Output',
};

function groupByCategory(
  templates: readonly TemplateMetadata[],
): Map<string, TemplateMetadata[]> {
  const grouped = new Map<string, TemplateMetadata[]>();
  for (const t of templates) {
    const existing = grouped.get(t.category);
    if (existing) {
      existing.push(t);
    } else {
      grouped.set(t.category, [t]);
    }
  }
  return grouped;
}

/** Flatten visible templates into an ordered list for keyboard nav */
function flattenVisibleItems(
  grouped: Map<string, TemplateMetadata[]>,
  isCategoryExpanded: (cat: string) => boolean,
): TemplateMetadata[] {
  const items: TemplateMetadata[] = [];
  for (const cat of categoryOrder) {
    const templates = grouped.get(cat);
    if (templates && isCategoryExpanded(cat)) {
      items.push(...templates);
    }
  }
  return items;
}

interface NodeLibraryPanelProps {
  readonly loading?: boolean;
  readonly readonly?: boolean;
}

/**
 * NodeLibraryPanel — Searchable, categorized, draggable node library.
 *
 * Design system section 9:
 * - Fixed left rail with sticky search
 * - Collapsible category sections
 * - Arrow key navigation + Enter to insert
 * - Compact/expanded display modes
 * - Loading skeletons
 * - Footer hint row
 */
export function NodeLibraryPanel({
  loading = false,
  readonly: isReadonly = false,
}: NodeLibraryPanelProps) {
  const searchQuery = useWorkflowStore((s) => s.libraryUi.searchQuery);
  const expandedCategoryIds = useWorkflowStore(
    (s) => s.libraryUi.expandedCategoryIds,
  );
  const displayMode = useWorkflowStore((s) => s.libraryUi.displayMode);
  const setLibraryUi = useWorkflowStore((s) => s.setLibraryUi);
  const addNode = useWorkflowStore((s) => s.addNode);

  const [focusedIndex, setFocusedIndex] = useState(-1);
  const scrollRef = useRef<HTMLDivElement>(null);

  const allTemplates = useMemo(() => getTemplateMetadata(), []);

  const filteredTemplates = useMemo(() => {
    if (!searchQuery.trim()) return allTemplates;
    const q = searchQuery.toLowerCase();
    return allTemplates.filter(
      (t) =>
        t.title.toLowerCase().includes(q) ||
        t.description.toLowerCase().includes(q) ||
        t.category.toLowerCase().includes(q) ||
        t.type.toLowerCase().includes(q),
    );
  }, [allTemplates, searchQuery]);

  const grouped = useMemo(
    () => groupByCategory(filteredTemplates),
    [filteredTemplates],
  );

  const isCategoryExpanded = useCallback(
    (category: string) => {
      if (expandedCategoryIds.length === 0 && !searchQuery.trim()) return true;
      if (searchQuery.trim()) return true;
      return expandedCategoryIds.includes(category);
    },
    [expandedCategoryIds, searchQuery],
  );

  const visibleItems = useMemo(
    () => flattenVisibleItems(grouped, isCategoryExpanded),
    [grouped, isCategoryExpanded],
  );

  const toggleCategory = (category: string) => {
    const current = expandedCategoryIds;
    const isExpanded = current.includes(category);
    setLibraryUi({
      expandedCategoryIds: isExpanded
        ? current.filter((c) => c !== category)
        : [...current, category],
    });
    setFocusedIndex(-1);
  };

  const toggleDisplayMode = () => {
    setLibraryUi({
      displayMode: displayMode === 'compact' ? 'expanded' : 'compact',
    });
  };

  const handleSearchChange = useCallback(
    (value: string) => {
      setLibraryUi({ searchQuery: value });
      setFocusedIndex(-1);
    },
    [setLibraryUi],
  );

  const handleInsertNode = useCallback(
    (templateType: string) => {
      if (isReadonly) return;
      const template = allTemplates.find((t) => t.type === templateType);
      if (!template) return;
      addNode({
        id: `node_${Date.now()}`,
        type: templateType,
        label: template.title,
        position: { x: 300, y: 200 },
        config: {},
      });
    },
    [allTemplates, addNode, isReadonly],
  );

  /** Arrow key navigation through visible items */
  const handleListKeyDown = useCallback(
    (event: React.KeyboardEvent) => {
      if (visibleItems.length === 0) return;

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setFocusedIndex((prev) =>
          prev < visibleItems.length - 1 ? prev + 1 : 0,
        );
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setFocusedIndex((prev) =>
          prev > 0 ? prev - 1 : visibleItems.length - 1,
        );
      } else if (event.key === 'Enter' && focusedIndex >= 0) {
        event.preventDefault();
        const item = visibleItems[focusedIndex];
        if (item) handleInsertNode(item.type);
      } else if (event.key === 'Escape') {
        setFocusedIndex(-1);
      }
    },
    [visibleItems, focusedIndex, handleInsertNode],
  );

  const isCompact = displayMode === 'compact';

  if (loading) {
    return (
      <Panel
        variant="ghost"
        className="flex h-full min-h-0 flex-col rounded-none border-0 border-r border-border bg-card"
        data-testid="node-library-panel"
      >
        <PanelHeader className="border-b border-border px-3 py-2">
          <Skeleton className="h-5 w-16" />
        </PanelHeader>
        <PanelContent className="flex flex-1 flex-col p-0">
          <div className="border-b border-border px-3 py-2">
            <Skeleton className="h-8 w-full rounded-md" />
          </div>
          <div className="space-y-2 px-3 py-2">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-10 w-full rounded-md" />
            ))}
          </div>
        </PanelContent>
      </Panel>
    );
  }

  return (
    <Panel
      variant="ghost"
      className="flex h-full min-h-0 flex-col rounded-none border-0 border-r border-border bg-card"
      data-testid="node-library-panel"
    >
      {/* Header */}
      <PanelHeader className="border-b border-border px-3 py-2">
        <div className="flex items-center justify-between">
          <PanelTitle className="text-sm font-medium">Nodes</PanelTitle>
          <Button
            variant="ghost"
            size="icon"
            className="h-6 w-6"
            onClick={toggleDisplayMode}
            aria-label={isCompact ? 'Switch to expanded view' : 'Switch to compact view'}
            aria-pressed={isCompact}
          >
            {isCompact ? (
              <LayoutGrid className="h-3.5 w-3.5" />
            ) : (
              <List className="h-3.5 w-3.5" />
            )}
          </Button>
        </div>
      </PanelHeader>

      <PanelContent className="flex flex-1 flex-col overflow-hidden p-0">
        {/* Sticky search */}
        <NodeSearch
          value={searchQuery}
          onChange={handleSearchChange}
          disabled={isReadonly}
        />

        {/* Category groups — scrollable with keyboard nav */}
        <div
          ref={scrollRef}
          className="flex-1 space-y-1 overflow-y-auto px-3 py-2"
          onKeyDown={handleListKeyDown}
          role="listbox"
          aria-label="Node templates"
          tabIndex={-1}
        >
          {/* Empty search state */}
          {filteredTemplates.length === 0 && (
            <div className="flex flex-col items-center gap-2 py-8 text-muted-foreground">
              <SearchX className="h-8 w-8 opacity-40" aria-hidden="true" />
              <p className="text-center text-xs">
                No nodes match &ldquo;{searchQuery}&rdquo;
              </p>
              <button
                type="button"
                onClick={() => handleSearchChange('')}
                className="rounded-md px-2 py-1 text-xs text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                Clear filters
              </button>
            </div>
          )}

          {categoryOrder
            .filter((cat) => grouped.has(cat))
            .map((category) => {
              const templates = grouped.get(category)!;
              const expanded = isCategoryExpanded(category);

              return (
                <div key={category}>
                  <button
                    type="button"
                    onClick={() => toggleCategory(category)}
                    aria-expanded={expanded}
                    aria-label={`${categoryLabels[category] ?? category} nodes (${templates.length})`}
                    className="flex w-full items-center gap-1 rounded px-1 py-1 text-xs font-medium uppercase tracking-wide text-muted-foreground transition-hover hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    {expanded ? (
                      <ChevronDown className="h-3 w-3" aria-hidden="true" />
                    ) : (
                      <ChevronRight className="h-3 w-3" aria-hidden="true" />
                    )}
                    {categoryLabels[category] ?? category}
                    <span className="ml-auto font-mono text-[10px]">
                      {templates.length}
                    </span>
                  </button>

                  {expanded && (
                    <div className="space-y-1 pb-1" role="group" aria-label={`${categoryLabels[category]} nodes`}>
                      {templates.map((template) => {
                        const globalIdx = visibleItems.indexOf(template);
                        return (
                          <NodeLibraryItem
                            key={template.type}
                            template={template}
                            compact={isCompact}
                            focused={globalIdx === focusedIndex}
                            readonly={isReadonly}
                            onInsert={handleInsertNode}
                          />
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
        </div>

        {/* Footer hint row */}
        <div className="border-t border-border px-3 py-1.5">
          <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground/70">
            <Keyboard className="h-3 w-3" aria-hidden="true" />
            <span>
              <kbd className="rounded border border-border px-1 font-mono">A</kbd>
              {' '}quick-add
              {' · '}
              <kbd className="rounded border border-border px-1 font-mono">↑↓</kbd>
              {' '}navigate
              {' · '}
              drag to canvas
            </span>
          </div>
        </div>
      </PanelContent>
    </Panel>
  );
}
