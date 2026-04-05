import { cn } from '@/shared/lib/utils';

interface SkeletonProps {
  readonly className?: string;
}

/**
 * Skeleton - Loading placeholder with shimmer animation.
 * Uses animate-shimmer (1400ms linear repeating) per design system section 8/14.
 */
export function Skeleton({ className }: SkeletonProps) {
  return (
    <div
      className={cn(
        'rounded-md bg-muted animate-shimmer',
        'bg-gradient-to-r from-muted via-muted-foreground/10 to-muted',
        className,
      )}
      aria-hidden="true"
    />
  );
}

/**
 * SkeletonText - Text-height skeleton line.
 */
export function SkeletonText({ className }: SkeletonProps) {
  return <Skeleton className={cn('h-3 w-full', className)} />;
}

/**
 * SkeletonBlock - Block-level skeleton for cards, previews.
 */
export function SkeletonBlock({ className }: SkeletonProps) {
  return <Skeleton className={cn('h-24 w-full', className)} />;
}
