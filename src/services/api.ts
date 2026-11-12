import { firebaseAuth } from './firebase';

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const base = import.meta.env.VITE_API_URL;
  if (!base) throw new Error('The service is not configured yet. Please try again later.');
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
  const token = await firebaseAuth?.currentUser?.getIdToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);
  const response = await fetch(`${base.replace(/\/$/, '')}/${path.replace(/^\//, '')}`, { ...options, headers });
  if (!response.ok) {
    const payload = await response.json().catch(() => null);
    throw new Error(payload?.message || `Request failed (${response.status}). Please try again.`);
  }
  return response.json() as Promise<T>;
}
