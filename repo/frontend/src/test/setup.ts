import '@testing-library/jest-dom/vitest';
import { afterEach, beforeEach, vi } from 'vitest';
import { cleanup } from '@testing-library/react';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

beforeEach(() => {
  // Each test gets a fresh fetch mock so call-order assertions are local.
  vi.stubGlobal('fetch', vi.fn());
});
