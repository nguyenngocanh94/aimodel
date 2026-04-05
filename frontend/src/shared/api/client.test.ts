import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { 
  apiRequest, 
  get, 
  post, 
  put, 
  patch, 
  del, 
  ApiError 
} from './client';

describe('API Client', () => {
  const mockFetch = vi.fn();
  
  beforeEach(() => {
    global.fetch = mockFetch;
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('apiRequest', () => {
    it('should make a GET request with correct headers', async () => {
      const mockData = { id: '1', name: 'Test' };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        statusText: 'OK',
        json: async () => mockData,
      });

      const result = await apiRequest('/workflows');

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/workflows',
        {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        }
      );
      expect(result).toEqual(mockData);
    });

    it('should make a POST request with body', async () => {
      const mockData = { id: '1', name: 'New Workflow' };
      const requestBody = { name: 'New Workflow' };
      
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 201,
        statusText: 'Created',
        json: async () => mockData,
      });

      const result = await apiRequest('/workflows', {
        method: 'POST',
        body: requestBody,
      });

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/workflows',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify(requestBody),
        }
      );
      expect(result).toEqual(mockData);
    });

    it('should handle 204 No Content response', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 204,
        statusText: 'No Content',
        json: async () => null,
      });

      const result = await apiRequest('/workflows/1', { method: 'DELETE' });

      expect(result).toBeUndefined();
    });

    it('should throw ApiError on 4xx response', async () => {
      const errorData = { error: 'Not Found', message: 'Workflow not found' };
      
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 404,
        statusText: 'Not Found',
        json: async () => errorData,
      });

      try {
        await apiRequest('/workflows/1');
        expect.fail('Should have thrown');
      } catch (error) {
        const apiError = error as ApiError;
        expect(apiError).toBeInstanceOf(ApiError);
        expect(apiError.message).toContain('Not Found');
        expect(apiError.status).toBe(404);
      }
    });

    it('should throw ApiError on 5xx response', async () => {
      const errorData = { error: 'Internal Server Error' };
      
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        json: async () => errorData,
      });

      try {
        await apiRequest('/workflows');
        expect.fail('Should have thrown');
      } catch (error) {
        expect(error).toBeInstanceOf(ApiError);
        expect(error.message).toContain('Internal Server Error');
        expect(error.status).toBe(500);
      }
    });

    it('should include custom headers', async () => {
      const mockData = { id: '1' };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        statusText: 'OK',
        json: async () => mockData,
      });

      await apiRequest('/workflows', {
        headers: { 'X-Custom-Header': 'value' },
      });

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'X-Custom-Header': 'value',
          }),
        })
      );
    });

    it('should handle absolute URLs', async () => {
      const mockData = { id: '1' };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        statusText: 'OK',
        json: async () => mockData,
      });

      const result = await apiRequest('http://example.com/api/test');

      expect(mockFetch).toHaveBeenCalledWith(
        'http://example.com/api/test',
        expect.any(Object),
      );
      expect(result).toEqual(mockData);
    });

    it('should parse error response correctly', async () => {
      const errorData = { 
        error: 'Validation Error',
        errors: { name: ['Name is required'] }
      };
      
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 422,
        statusText: 'Unprocessable Entity',
        json: async () => errorData,
      });

      try {
        await apiRequest('/workflows', { method: 'POST', body: {} });
        expect.fail('Should have thrown');
      } catch (error) {
        expect(error).toBeInstanceOf(ApiError);
        expect(error.status).toBe(422);
        expect(error.data).toEqual(errorData);
      }
    });
  });

  describe('convenience methods', () => {
    it('get should call apiRequest with GET method', async () => {
      const mockData = { id: '1' };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      await get('/workflows');

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({ method: 'GET' })
      );
    });

    it('post should call apiRequest with POST method', async () => {
      const mockData = { id: '1' };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => mockData,
      });

      await post('/workflows', { name: 'Test' });

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({ 
          method: 'POST',
          body: JSON.stringify({ name: 'Test' })
        })
      );
    });

    it('put should call apiRequest with PUT method', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      });

      await put('/workflows/1', { name: 'Updated' });

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({ method: 'PUT' })
      );
    });

    it('patch should call apiRequest with PATCH method', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      });

      await patch('/workflows/1', { name: 'Updated' });

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({ method: 'PATCH' })
      );
    });

    it('del should call apiRequest with DELETE method', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 204,
      });

      await del('/workflows/1');

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({ method: 'DELETE' })
      );
    });
  });
});
