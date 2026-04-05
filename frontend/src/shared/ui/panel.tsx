import * as React from 'react'
import { cva, type VariantProps } from 'class-variance-authority'
import { X } from 'lucide-react'

import { cn } from '@/shared/lib/utils'

const panelVariants = cva(
  'rounded-lg border bg-card text-card-foreground shadow-sm',
  {
    variants: {
      variant: {
        default: '',
        ghost: 'border-0 shadow-none',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)

const Panel = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement> & VariantProps<typeof panelVariants>
>(({ className, variant, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(panelVariants({ variant }), className)}
    {...props}
  />
))
Panel.displayName = 'Panel'

const PanelHeader = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('flex flex-col space-y-1.5 p-6', className)}
    {...props}
  />
))
PanelHeader.displayName = 'PanelHeader'

const PanelTitle = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLHeadingElement>
>(({ className, ...props }, ref) => (
  <h3
    ref={ref}
    className={cn(
      'text-2xl font-semibold leading-none tracking-tight',
      className
    )}
    {...props}
  />
))
PanelTitle.displayName = 'PanelTitle'

const PanelDescription = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLParagraphElement>
>(({ className, ...props }, ref) => (
  <p
    ref={ref}
    className={cn('text-sm text-muted-foreground', className)}
    {...props}
  />
))
PanelDescription.displayName = 'PanelDescription'

const PanelContent = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div ref={ref} className={cn('p-6 pt-0', className)} {...props} />
))
PanelContent.displayName = 'PanelContent'

const PanelFooter = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('flex items-center p-6 pt-0', className)}
    {...props}
  />
))
PanelFooter.displayName = 'PanelFooter'

interface PanelCloseButtonProps {
  onClose?: () => void
  className?: string
}

function PanelCloseButton({ onClose, className }: PanelCloseButtonProps) {
  return (
    <button
      onClick={onClose}
      className={cn(
        'absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground',
        className
      )}
    >
      <X className="h-4 w-4" />
      <span className="sr-only">Close</span>
    </button>
  )
}

export {
  Panel,
  PanelHeader,
  PanelFooter,
  PanelTitle,
  PanelDescription,
  PanelContent,
  PanelCloseButton,
  panelVariants,
}
