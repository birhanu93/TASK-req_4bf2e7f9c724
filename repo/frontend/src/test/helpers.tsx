import { MemoryRouter } from 'react-router-dom';
import type { PropsWithChildren } from 'react';
import { AuthProvider } from '../lib/auth';

interface Options {
  initialEntries?: string[];
}

export function withProviders(options: Options = {}) {
  const initialEntries = options.initialEntries ?? ['/'];
  return function Providers({ children }: PropsWithChildren) {
    return (
      <MemoryRouter initialEntries={initialEntries}>
        <AuthProvider>{children}</AuthProvider>
      </MemoryRouter>
    );
  };
}

export interface MockResponse {
  status?: number;
  body?: unknown;
}

/**
 * Installs a predictable fetch double that matches requests by
 * "METHOD /path". The fetch tuple is recorded so tests can assert exactly
 * which backend calls fired, in which order, and with which body.
 */
export function installFetchMock(map: Record<string, MockResponse | ((body: unknown) => MockResponse)>) {
  const calls: Array<{ method: string; url: string; body: unknown }> = [];
  const mock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = typeof input === 'string' ? input : input.toString();
    const method = (init?.method ?? 'GET').toUpperCase();
    const rawBody = init?.body;
    const body = typeof rawBody === 'string' && rawBody.length > 0 ? JSON.parse(rawBody) : undefined;
    calls.push({ method, url, body });
    const key = `${method} ${url}`;
    const entry = map[key];
    if (!entry) {
      return new Response(JSON.stringify({ error: `unmapped ${key}` }), { status: 500 });
    }
    const resolved = typeof entry === 'function' ? entry(body) : entry;
    const status = resolved.status ?? 200;
    if (status === 204 || resolved.body === undefined) {
      return new Response(null, { status });
    }
    return new Response(JSON.stringify(resolved.body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    });
  });
  vi.stubGlobal('fetch', mock);
  return { mock, calls };
}
