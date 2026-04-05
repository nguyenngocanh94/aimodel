import * as React from 'react'
import { Group, Panel, Separator } from 'react-resizable-panels'
import { GripVertical } from 'lucide-react'

import { cn } from '@/shared/lib/utils'

const ResizablePanelGroup = ({
  className,
  autoSave,
  ...props
}: React.ComponentProps<typeof Group> & {
  readonly autoSave?: string | boolean
}) => (
  <Group className={cn('flex h-full w-full', className)} autoSave={autoSave} {...props} />
)

const ResizablePanel = Panel

const ResizableHandle = ({
  withHandle,
  className,
  ...props
}: React.ComponentProps<typeof Separator> & {
  readonly withHandle?: boolean
}) => (
  <Separator
    className={cn(
      'relative flex w-px items-center justify-center bg-border after:absolute after:inset-y-0 after:left-1/2 after:w-[10px] after:-translate-x-1/2 hover:bg-primary/30 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring focus-visible:ring-offset-1 transition-colors',
      className
    )}
    {...props}
  >
    {withHandle ? (
      <div className="z-10 flex h-4 w-3 items-center justify-center rounded-sm border bg-border" aria-hidden="true">
        <GripVertical className="h-2.5 w-2.5" />
      </div>
    ) : null}
  </Separator>
)

export { ResizablePanelGroup, ResizablePanel, ResizableHandle }
