import { useMemo } from 'react';
import { Search, ChevronDown, ChevronRight, LayoutGrid, List } from 'lucide-react';
import { Panel, PanelContent, PanelHeader, PanelTitle } from '@/shared/ui/panel';
import { Button } from '@/shared/ui/button';
import { getTemplateMetadata, type TemplateMetadata } from '@/features/node-registry/node-registry';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { NodeLibraryItem } from './node-library-item';

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

/**
 * NodeLibraryPanel - Searchable node library sidebar
 *
 * Features per plan section 6.2:
 * - Search input
 * - Category filters with expand/collapse
 * - Compact/expanded display modes
 * - Draggable items for canvas drop
 */
export function NodeLibraryPanel() {
  const searchQuery = useWorkflowStore((s) => s.libraryUi.searchQuery);
  const expandedCategoryIds = useWorkflowStore(
    (s) => s.libraryUi.expandedCategoryIds,
  );
  const displayMode = useWorkflowStore((s) => s.libraryUi.displayMode);
  const setLibraryUi = useWorkflowStore((s) => s.setLibraryUi);

  const allTemplates = useMemo(() => getTemplateMetadata(), []);

  // Filter by search query
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

  const toggleCategory = (category: string) => {
    const current = expandedCategoryIds;
    const isExpanded = current.includes(category);
    setLibraryUi({
      expandedCategoryIds: isExpanded
        ? current.filter((c) => c !== category)
        : [...current, category],
    });
  };

  const isCategoryExpanded = (category: string) => {
    // When no categories are explicitly expanded and no search, default all open
    if (expandedCategoryIds.length === 0 && !searchQuery.trim()) return true;
    // When searching, show all
    if (searchQuery.trim()) return true;
    return expandedCategoryIds.includes(category);
  };

  const toggleDisplayMode = () => {
    setLibraryUi({
      displayMode: displayMode === 'compact' ? 'expanded' : 'compact',
    });
  };

  const isCompact = displayMode === 'compact';

  return (
    <Panel
      variant="ghost"
      className="flex h-full min-h-0 flex-col rounded-none border-0 border-r"
    >
      <PanelHeader className="border-b px-3 py-2">
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
        {/* Search */}
        <div className="border-b px-3 py-2">
          <div className="relative">
            <Search className="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search nodes..."
              aria-label="Search nodes"
              value={searchQuery}
              onChange={(e) => setLibraryUi({ searchQuery: e.target.value })}
              className="h-8 w-full rounded-md border bg-background pl-8 pr-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>
        </div>

        {/* Category groups */}
        <div className="flex-1 overflow-y-auto px-3 py-2 space-y-1">
          {filteredTemplates.length === 0 && (
            <p className="text-xs text-muted-foreground text-center py-4">
              No nodes match &ldquo;{searchQuery}&rdquo;
            </p>
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
                    className="flex w-full items-center gap-1 rounded px-1 py-1 text-xs font-medium text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 transition-colors"
                  >
                    {expanded ? (
                      <ChevronDown className="h-3 w-3" aria-hidden="true" />
                    ) : (
                      <ChevronRight className="h-3 w-3" aria-hidden="true" />
                    )}
                    {categoryLabels[category] ?? category}
                    <span className="ml-auto text-[10px]">
                      {templates.length}
                    </span>
                  </button>

                  {expanded && (
                    <div className="space-y-1 pb-1">
                      {templates.map((template) => (
                        <NodeLibraryItem
                          key={template.type}
                          template={template}
                          compact={isCompact}
                        />
                      ))}
                    </div>
                  )}
                </div>
              );
            })}
        </div>
      </PanelContent>
    </Panel>
  );
}
