import { afterEach, describe, expect, test, vi } from 'vitest';

import { api } from '../../src/services/api';

vi.mock('../../src/services/firebase', () => ({ firebaseAuth: null }));
afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});
describe('API requests', () => {
  test('fails clearly when no backend is configured', async () => {
    vi.stubEnv('VITE_API_URL', '');
    await expect(api('shops')).rejects.toThrow('not configured');
  });
  test('retains backend validation errors', async () => {
    vi.stubEnv('VITE_API_URL', 'https://api.example.test/api/v1');
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValue(
          new Response(JSON.stringify({ message: 'Inventory is no longer available.' }), { status: 409 }),
        ),
    );
    await expect(api('reservations')).rejects.toThrow('Inventory is no longer available.');
  });
  test('does not set a JSON content type on file uploads', async () => {
    vi.stubEnv('VITE_API_URL', 'https://api.example.test/api/v1');
    const fetchMock = vi.fn().mockResolvedValue(new Response('{}', { status: 201 }));
    vi.stubGlobal('fetch', fetchMock);
    await api('files', { method: 'POST', body: new FormData() });
    expect(fetchMock.mock.calls[0][1].headers.has('Content-Type')).toBe(false);
  });
});
