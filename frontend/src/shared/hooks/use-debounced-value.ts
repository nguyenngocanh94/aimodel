/**
 * useDebounced - Generic debounce hook for expensive computations
 * AiModel-537.6
 */

import { useState, useEffect, useRef } from 'react'

/**
 * Returns a debounced version of the input value.
 * The output value updates only after `delayMs` of silence.
 */
export function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs)
    return () => clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}

/**
 * Returns a debounced computation result.
 * Recomputes `compute(value)` only after `delayMs` of silence on the input.
 * Returns the previous result during the debounce window.
 */
export function useDebouncedComputation<TInput, TOutput>(
  input: TInput,
  compute: (input: TInput) => TOutput,
  delayMs: number,
): TOutput {
  const debouncedInput = useDebouncedValue(input, delayMs)
  const [result, setResult] = useState(() => compute(debouncedInput))
  const computeRef = useRef(compute)
  computeRef.current = compute

  useEffect(() => {
    setResult(computeRef.current(debouncedInput))
  }, [debouncedInput])

  return result
}
