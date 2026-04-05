/**
 * API Client - fetch wrapper with error handling
 * Base URL configurable via VITE_API_BASE_URL env var
 */

import { ApiErrorSchema } from './schemas';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

export interface ApiClientOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  headers?: Record<string, string>;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly data: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

/**
 * Make an API request with fetch
 * @throws ApiError on non-2xx responses
 */
export async function apiRequest<T>(
  endpoint: string,
  options: ApiClientOptions = {},
): Promise<T> {
  const url = endpoint.startsWith('http') ? endpoint : `${API_BASE_URL}${endpoint}`;
  
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  const config: RequestInit = {
    method: options.method || 'GET',
    headers,
  };

  if (options.body && options.method !== 'GET') {
    config.body = JSON.stringify(options.body);
  }

  const response = await fetch(url, config);
  
  // Handle empty responses (204 No Content)
  if (response.status === 204) {
    return undefined as T;
  }

  let data: unknown;
  
  try {
    data = await response.json();
  } catch {
    // If JSON parsing fails, use text
    const text = await response.text();
    data = text;
  }

  if (!response.ok) {
    // Try to parse error response
    let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
    
    if (typeof data === 'object' && data !== null) {
      const errorParse = ApiErrorSchema.safeParse(data);
      if (errorParse.success) {
        errorMessage = errorParse.data.error || errorMessage;
      }
    }
    
    throw new ApiError(errorMessage, response.status, data);
  }

  return data as T;
}

// ============================================================
// Convenience methods
// ============================================================

export function get<T>(endpoint: string, headers?: Record<string, string>): Promise<T> {
  return apiRequest<T>(endpoint, { method: 'GET', headers });
}

export function post<T>(endpoint: string, body: unknown, headers?: Record<string, string>): Promise<T> {
  return apiRequest<T>(endpoint, { method: 'POST', body, headers });
}

export function put<T>(endpoint: string, body: unknown, headers?: Record<string, string>): Promise<T> {
  return apiRequest<T>(endpoint, { method: 'PUT', body, headers });
}

export function patch<T>(endpoint: string, body: unknown, headers?: Record<string, string>): Promise<T> {
  return apiRequest<T>(endpoint, { method: 'PATCH', body, headers });
}

export function del<T>(endpoint: string, headers?: Record<string, string>): Promise<T> {
  return apiRequest<T>(endpoint, { method: 'DELETE', headers });
}
