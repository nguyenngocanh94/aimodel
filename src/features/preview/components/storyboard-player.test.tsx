import { render, screen, act } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act as actHook } from '@testing-library/react'
import { StoryboardPlayer, useStoryboardPlayback } from './storyboard-player'
import type { VideoAssetPayload } from '@/features/node-registry/templates/video-composer'

// ============================================================
// Test data
// ============================================================

function makeVideoAsset(overrides?: Partial<VideoAssetPayload>): VideoAssetPayload {
  return {
    timeline: [
      { index: 0, type: 'titleCard', durationSeconds: 3 },
      { index: 1, type: 'image', assetRef: 'asset-0', durationSeconds: 4, transition: 'fade' },
      { index: 2, type: 'transition', durationSeconds: 0.5, transition: 'fade' },
      { index: 3, type: 'image', assetRef: 'asset-1', durationSeconds: 4, transition: 'fade' },
    ],
    totalDurationSeconds: 11.5,
    aspectRatio: '16:9',
    fps: 30,
    posterFrameUrl: 'placeholder://video/poster/test.jpg',
    storyboardPreview: {
      frameCount: 3,
      frameDurationMs: 3833,
      transitionStyle: 'fade',
    },
    hasAudio: false,
    hasSubtitles: false,
    musicBed: 'none',
    ...overrides,
  }
}

function makeEmptyVideoAsset(): VideoAssetPayload {
  return makeVideoAsset({
    timeline: [],
    totalDurationSeconds: 0,
    storyboardPreview: { frameCount: 0, frameDurationMs: 0, transitionStyle: 'fade' },
  })
}

// ============================================================
// Component tests
// ============================================================

describe('StoryboardPlayer', () => {
  it('should render with test-id', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset()} />)
    expect(screen.getByTestId('storyboard-player')).toBeInTheDocument()
  })

  it('should render play/pause button', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset()} />)
    expect(screen.getByTestId('play-pause-btn')).toBeInTheDocument()
    expect(screen.getByLabelText('Play')).toBeInTheDocument()
  })

  it('should render poster frame when not playing', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset()} />)
    expect(screen.getByTestId('poster-frame')).toBeInTheDocument()
  })

  it('should render metadata badges', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset()} />)
    const badges = screen.getByTestId('metadata-badges')
    expect(badges).toBeInTheDocument()
    expect(badges.textContent).toContain('11.5s')
    expect(badges.textContent).toContain('16:9')
    expect(badges.textContent).toContain('30fps')
    expect(badges.textContent).toContain('fade')
  })

  it('should render audio badge when hasAudio', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset({ hasAudio: true })} />)
    expect(screen.getByTestId('metadata-badges').textContent).toContain('audio')
  })

  it('should render subtitles badge when hasSubtitles', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset({ hasSubtitles: true })} />)
    expect(screen.getByTestId('metadata-badges').textContent).toContain('subs')
  })

  it('should render music badge when musicBed is not none', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset({ musicBed: 'placeholder' })} />)
    expect(screen.getByTestId('metadata-badges').textContent).toContain('music')
  })

  it('should render progress bar', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset()} />)
    expect(screen.getByTestId('progress-bar')).toBeInTheDocument()
  })

  it('should render time display', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset()} />)
    expect(screen.getByTestId('time-display').textContent).toBe('0:00 / 0:11')
  })

  it('should render restart button', () => {
    render(<StoryboardPlayer videoAsset={makeVideoAsset()} />)
    expect(screen.getByLabelText('Restart')).toBeInTheDocument()
  })
})

describe('StoryboardPlayer - empty state', () => {
  it('should render empty state when timeline is empty', () => {
    render(<StoryboardPlayer videoAsset={makeEmptyVideoAsset()} />)
    expect(screen.getByTestId('storyboard-empty')).toBeInTheDocument()
    expect(screen.getByText('No visual assets')).toBeInTheDocument()
  })

  it('should render metadata-only badge in empty state', () => {
    render(<StoryboardPlayer videoAsset={makeEmptyVideoAsset()} />)
    expect(screen.getByTestId('metadata-badges').textContent).toContain('metadata only')
  })

  it('should not render play controls in empty state', () => {
    render(<StoryboardPlayer videoAsset={makeEmptyVideoAsset()} />)
    expect(screen.queryByTestId('play-pause-btn')).not.toBeInTheDocument()
  })
})

// ============================================================
// Hook tests
// ============================================================

describe('useStoryboardPlayback', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const timeline = [
    { index: 0, type: 'titleCard' as const, durationSeconds: 2 },
    { index: 1, type: 'image' as const, assetRef: 'a0', durationSeconds: 3 },
  ]

  it('should start not playing', () => {
    const { result } = renderHook(() => useStoryboardPlayback(timeline, 5))
    expect(result.current.isPlaying).toBe(false)
    expect(result.current.currentEntryIndex).toBe(0)
    expect(result.current.elapsedMs).toBe(0)
  })

  it('should play and advance elapsed time', () => {
    const { result } = renderHook(() => useStoryboardPlayback(timeline, 5))

    actHook(() => result.current.play())
    expect(result.current.isPlaying).toBe(true)

    // Advance 1 second
    actHook(() => vi.advanceTimersByTime(1000))
    expect(result.current.elapsedMs).toBe(1000)
    // Still on first entry (title card, 2s duration)
    expect(result.current.currentEntryIndex).toBe(0)
  })

  it('should advance to next entry after entry duration', () => {
    const { result } = renderHook(() => useStoryboardPlayback(timeline, 5))

    actHook(() => result.current.play())
    // Advance past first entry (2s)
    actHook(() => vi.advanceTimersByTime(2100))
    expect(result.current.currentEntryIndex).toBe(1)
  })

  it('should stop when reaching end', () => {
    const { result } = renderHook(() => useStoryboardPlayback(timeline, 5))

    actHook(() => result.current.play())
    actHook(() => vi.advanceTimersByTime(5100))
    expect(result.current.isPlaying).toBe(false)
    expect(result.current.elapsedMs).toBe(5000)
  })

  it('should reset to start', () => {
    const { result } = renderHook(() => useStoryboardPlayback(timeline, 5))

    actHook(() => result.current.play())
    actHook(() => vi.advanceTimersByTime(2000))

    actHook(() => result.current.reset())
    expect(result.current.isPlaying).toBe(false)
    expect(result.current.currentEntryIndex).toBe(0)
    expect(result.current.elapsedMs).toBe(0)
  })

  it('should toggle play/pause', () => {
    const { result } = renderHook(() => useStoryboardPlayback(timeline, 5))

    actHook(() => result.current.togglePlayPause())
    expect(result.current.isPlaying).toBe(true)

    actHook(() => result.current.togglePlayPause())
    expect(result.current.isPlaying).toBe(false)
  })

  it('should not play with empty timeline', () => {
    const { result } = renderHook(() => useStoryboardPlayback([], 0))

    actHook(() => result.current.play())
    expect(result.current.isPlaying).toBe(false)
  })
})
