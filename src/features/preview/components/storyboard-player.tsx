/**
 * StoryboardPlayer - AiModel-537.7
 * Animated storyboard preview player for videoComposer output.
 * Per plan sections 5.9 and 18.2.1
 */

import { useState, useEffect, useRef, useCallback } from 'react'
import { Play, Pause, SkipBack, Film, Volume2, Subtitles, Clock } from 'lucide-react'
import { Button } from '@/shared/ui/button'
import { Badge } from '@/shared/ui/badge'
import { cn } from '@/shared/lib/utils'
import type { VideoAssetPayload } from '@/features/node-registry/templates/video-composer'

interface TimelineEntry {
  readonly index: number
  readonly type: 'image' | 'transition' | 'titleCard'
  readonly assetRef?: string
  readonly durationSeconds: number
  readonly transition?: string
}

// ============================================================
// Aspect ratio utilities
// ============================================================

const aspectRatioClasses: Record<string, string> = {
  '16:9': 'aspect-video',
  '9:16': 'aspect-[9/16]',
  '1:1': 'aspect-square',
  '4:3': 'aspect-[4/3]',
}

// ============================================================
// Player hook
// ============================================================

export interface StoryboardPlayerState {
  readonly isPlaying: boolean
  readonly currentEntryIndex: number
  readonly elapsedMs: number
  readonly totalDurationMs: number
}

export function useStoryboardPlayback(timeline: readonly TimelineEntry[], totalDurationSeconds: number) {
  const [isPlaying, setIsPlaying] = useState(false)
  const [currentEntryIndex, setCurrentEntryIndex] = useState(0)
  const [elapsedMs, setElapsedMs] = useState(0)
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null)
  const totalDurationMs = totalDurationSeconds * 1000

  const stop = useCallback(() => {
    setIsPlaying(false)
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
  }, [])

  const reset = useCallback(() => {
    stop()
    setCurrentEntryIndex(0)
    setElapsedMs(0)
  }, [stop])

  const play = useCallback(() => {
    if (timeline.length === 0) return
    setIsPlaying(true)
  }, [timeline.length])

  const togglePlayPause = useCallback(() => {
    if (isPlaying) {
      stop()
    } else {
      play()
    }
  }, [isPlaying, stop, play])

  // Advance playback timer
  useEffect(() => {
    if (!isPlaying || timeline.length === 0) return

    const TICK_MS = 100
    timerRef.current = setInterval(() => {
      setElapsedMs((prev) => {
        const next = prev + TICK_MS
        if (next >= totalDurationMs) {
          stop()
          return totalDurationMs
        }
        return next
      })
    }, TICK_MS)

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current)
        timerRef.current = null
      }
    }
  }, [isPlaying, timeline.length, totalDurationMs, stop])

  // Compute current entry from elapsed time
  useEffect(() => {
    let cumulativeMs = 0
    for (let i = 0; i < timeline.length; i++) {
      cumulativeMs += timeline[i].durationSeconds * 1000
      if (elapsedMs < cumulativeMs) {
        setCurrentEntryIndex(i)
        return
      }
    }
    // Past end — stay on last entry
    if (timeline.length > 0) {
      setCurrentEntryIndex(timeline.length - 1)
    }
  }, [elapsedMs, timeline])

  return {
    isPlaying,
    currentEntryIndex,
    elapsedMs,
    totalDurationMs,
    play,
    stop,
    reset,
    togglePlayPause,
  }
}

// ============================================================
// Subtitle overlay
// ============================================================

function formatTime(ms: number): string {
  const totalSeconds = Math.floor(ms / 1000)
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

// ============================================================
// Empty state
// ============================================================

function StoryboardEmptyState() {
  return (
    <div
      className="flex flex-col items-center justify-center h-full text-center p-4"
      data-testid="storyboard-empty"
    >
      <Film className="h-8 w-8 text-muted-foreground mb-2" aria-hidden="true" />
      <p className="text-sm font-medium text-muted-foreground">No visual assets</p>
      <p className="text-xs text-muted-foreground mt-1">
        Run the workflow to generate video frames.
      </p>
    </div>
  )
}

// ============================================================
// Scene frame renderer
// ============================================================

function SceneFrame({
  entry,
  transition,
  isActive,
}: {
  readonly entry: TimelineEntry
  readonly transition?: string
  readonly isActive: boolean
}) {
  if (entry.type === 'titleCard') {
    return (
      <div
        className={cn(
          'absolute inset-0 flex items-center justify-center bg-black text-white transition-opacity duration-300',
          isActive ? 'opacity-100' : 'opacity-0',
        )}
        data-testid="scene-title-card"
      >
        <div className="text-center">
          <Film className="h-6 w-6 mx-auto mb-2 text-white/60" aria-hidden="true" />
          <p className="text-sm font-medium">Title Card</p>
        </div>
      </div>
    )
  }

  if (entry.type === 'transition') {
    return (
      <div
        className={cn(
          'absolute inset-0 flex items-center justify-center bg-black/80 transition-opacity duration-300',
          isActive ? 'opacity-100' : 'opacity-0',
        )}
        data-testid="scene-transition"
      >
        <p className="text-xs text-white/60 uppercase tracking-wide">{transition ?? entry.transition ?? 'transition'}</p>
      </div>
    )
  }

  // Image frame — display placeholder
  return (
    <div
      className={cn(
        'absolute inset-0 flex items-center justify-center transition-opacity',
        transition === 'fade' ? 'duration-500' : 'duration-100',
        isActive ? 'opacity-100' : 'opacity-0',
      )}
      data-testid="scene-image"
    >
      <div className="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-900" />
      <div className="relative text-center text-white/80">
        <p className="text-xs font-mono">{entry.assetRef ?? `Frame ${entry.index}`}</p>
      </div>
    </div>
  )
}

// ============================================================
// Main component
// ============================================================

interface StoryboardPlayerProps {
  readonly videoAsset: VideoAssetPayload
}

export function StoryboardPlayer({ videoAsset }: StoryboardPlayerProps) {
  const { timeline, totalDurationSeconds, aspectRatio, fps, posterFrameUrl, storyboardPreview, hasAudio, hasSubtitles, musicBed } = videoAsset

  const imageEntries = timeline.filter((e) => e.type === 'image')
  const isEmpty = imageEntries.length === 0

  const {
    isPlaying,
    currentEntryIndex,
    elapsedMs,
    totalDurationMs,
    togglePlayPause,
    reset,
  } = useStoryboardPlayback(timeline, totalDurationSeconds)

  const currentEntry = timeline[currentEntryIndex]
  const progress = totalDurationMs > 0 ? (elapsedMs / totalDurationMs) * 100 : 0
  const aspectClass = aspectRatioClasses[aspectRatio] ?? 'aspect-video'

  if (isEmpty) {
    return (
      <div data-testid="storyboard-player">
        <div className={cn('relative bg-muted rounded-lg overflow-hidden', aspectClass)}>
          <StoryboardEmptyState />
        </div>
        {/* Show metadata even if no visuals */}
        <MetadataBadges
          aspectRatio={aspectRatio}
          fps={fps}
          totalDurationSeconds={totalDurationSeconds}
          transitionStyle={storyboardPreview.transitionStyle}
          hasAudio={hasAudio}
          hasSubtitles={hasSubtitles}
          musicBed={musicBed}
          metadataOnly
        />
      </div>
    )
  }

  return (
    <div data-testid="storyboard-player" className="space-y-2">
      {/* Viewport */}
      <div className={cn('relative bg-black rounded-lg overflow-hidden', aspectClass)}>
        {/* Scene frames */}
        {timeline.map((entry, i) => (
          <SceneFrame
            key={entry.index}
            entry={entry}
            transition={storyboardPreview.transitionStyle}
            isActive={i === currentEntryIndex}
          />
        ))}

        {/* Poster frame overlay when not playing and at start */}
        {!isPlaying && elapsedMs === 0 && (
          <div
            className="absolute inset-0 flex items-center justify-center bg-black/60"
            data-testid="poster-frame"
          >
            <div className="text-center text-white">
              <Film className="h-8 w-8 mx-auto mb-1 text-white/60" aria-hidden="true" />
              <p className="text-xs text-white/60">{posterFrameUrl}</p>
            </div>
          </div>
        )}

        {/* Time display */}
        <div className="absolute bottom-2 right-2 bg-black/60 rounded px-1.5 py-0.5">
          <span className="text-[10px] text-white font-mono" data-testid="time-display">
            {formatTime(elapsedMs)} / {formatTime(totalDurationMs)}
          </span>
        </div>

        {/* Current scene indicator */}
        {currentEntry && (
          <div className="absolute top-2 left-2 bg-black/60 rounded px-1.5 py-0.5">
            <span className="text-[10px] text-white" data-testid="scene-indicator">
              {currentEntry.type === 'titleCard' ? 'Title Card' :
               currentEntry.type === 'transition' ? `Transition (${currentEntry.transition ?? storyboardPreview.transitionStyle})` :
               `Scene ${currentEntry.index + 1}`}
            </span>
          </div>
        )}
      </div>

      {/* Progress bar */}
      <div
        className="h-1 bg-muted rounded-full overflow-hidden cursor-pointer"
        data-testid="progress-bar"
        role="progressbar"
        aria-valuenow={Math.round(progress)}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label="Playback progress"
      >
        <div
          className="h-full bg-primary transition-all duration-100"
          style={{ width: `${progress}%` }}
        />
      </div>

      {/* Controls */}
      <div className="flex items-center gap-1">
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7"
          onClick={reset}
          aria-label="Restart"
        >
          <SkipBack className="h-3.5 w-3.5" />
        </Button>
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7"
          onClick={togglePlayPause}
          aria-label={isPlaying ? 'Pause' : 'Play'}
          data-testid="play-pause-btn"
        >
          {isPlaying ? (
            <Pause className="h-3.5 w-3.5" />
          ) : (
            <Play className="h-3.5 w-3.5" />
          )}
        </Button>
      </div>

      {/* Metadata badges */}
      <MetadataBadges
        aspectRatio={aspectRatio}
        fps={fps}
        totalDurationSeconds={totalDurationSeconds}
        transitionStyle={storyboardPreview.transitionStyle}
        hasAudio={hasAudio}
        hasSubtitles={hasSubtitles}
        musicBed={musicBed}
      />
    </div>
  )
}

// ============================================================
// Metadata badges
// ============================================================

function MetadataBadges({
  aspectRatio,
  fps,
  totalDurationSeconds,
  transitionStyle,
  hasAudio,
  hasSubtitles,
  musicBed,
  metadataOnly = false,
}: {
  readonly aspectRatio: string
  readonly fps: number
  readonly totalDurationSeconds: number
  readonly transitionStyle: string
  readonly hasAudio: boolean
  readonly hasSubtitles: boolean
  readonly musicBed: string
  readonly metadataOnly?: boolean
}) {
  return (
    <div className="flex flex-wrap gap-1" data-testid="metadata-badges">
      {metadataOnly && (
        <Badge variant="secondary" className="text-[10px]">
          metadata only
        </Badge>
      )}
      <Badge variant="outline" className="text-[10px] gap-0.5">
        <Clock className="h-2.5 w-2.5" aria-hidden="true" />
        {totalDurationSeconds}s
      </Badge>
      <Badge variant="outline" className="text-[10px]">
        {aspectRatio}
      </Badge>
      <Badge variant="outline" className="text-[10px]">
        {fps}fps
      </Badge>
      <Badge variant="outline" className="text-[10px]">
        {transitionStyle}
      </Badge>
      {hasAudio && (
        <Badge variant="outline" className="text-[10px] gap-0.5">
          <Volume2 className="h-2.5 w-2.5" aria-hidden="true" />
          audio
        </Badge>
      )}
      {hasSubtitles && (
        <Badge variant="outline" className="text-[10px] gap-0.5">
          <Subtitles className="h-2.5 w-2.5" aria-hidden="true" />
          subs
        </Badge>
      )}
      {musicBed !== 'none' && (
        <Badge variant="outline" className="text-[10px]">
          music
        </Badge>
      )}
    </div>
  )
}
