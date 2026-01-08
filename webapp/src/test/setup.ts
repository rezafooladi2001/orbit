/**
 * Vitest Test Setup
 * Configures the testing environment with DOM matchers and mocks
 */

import '@testing-library/jest-dom';
import { vi } from 'vitest';

// Mock Telegram WebApp
const mockTelegramWebApp = {
  initData: 'user=%7B%22id%22%3A123456789%7D&hash=abc123',
  initDataUnsafe: {
    user: {
      id: 123456789,
      first_name: 'Test',
      last_name: 'User',
      username: 'testuser',
      language_code: 'en',
    },
    auth_date: Math.floor(Date.now() / 1000),
    hash: 'abc123',
  },
  version: '6.0',
  platform: 'web',
  colorScheme: 'dark',
  themeParams: {
    bg_color: '#0a0c10',
    text_color: '#ffffff',
    hint_color: '#94a3b8',
    link_color: '#10b981',
    button_color: '#10b981',
    button_text_color: '#ffffff',
  },
  isExpanded: true,
  viewportHeight: 800,
  viewportStableHeight: 800,
  MainButton: {
    text: '',
    color: '#10b981',
    textColor: '#ffffff',
    isVisible: false,
    isActive: true,
    isProgressVisible: false,
    setText: vi.fn(),
    onClick: vi.fn(),
    offClick: vi.fn(),
    show: vi.fn(),
    hide: vi.fn(),
    enable: vi.fn(),
    disable: vi.fn(),
    showProgress: vi.fn(),
    hideProgress: vi.fn(),
  },
  HapticFeedback: {
    impactOccurred: vi.fn(),
    notificationOccurred: vi.fn(),
    selectionChanged: vi.fn(),
  },
  BackButton: {
    isVisible: false,
    onClick: vi.fn(),
    offClick: vi.fn(),
    show: vi.fn(),
    hide: vi.fn(),
  },
  close: vi.fn(),
  expand: vi.fn(),
  ready: vi.fn(),
  showPopup: vi.fn(),
  showAlert: vi.fn(),
  showConfirm: vi.fn(),
  setHeaderColor: vi.fn(),
  setBackgroundColor: vi.fn(),
  enableClosingConfirmation: vi.fn(),
  disableClosingConfirmation: vi.fn(),
  openLink: vi.fn(),
  openTelegramLink: vi.fn(),
  openInvoice: vi.fn(),
  sendData: vi.fn(),
};

// Set up window.Telegram
Object.defineProperty(window, 'Telegram', {
  value: { WebApp: mockTelegramWebApp },
  writable: true,
});

// Mock matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

// Mock ResizeObserver
class MockResizeObserver {
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
}
Object.defineProperty(window, 'ResizeObserver', {
  writable: true,
  value: MockResizeObserver,
});

// Mock IntersectionObserver
class MockIntersectionObserver {
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
}
Object.defineProperty(window, 'IntersectionObserver', {
  writable: true,
  value: MockIntersectionObserver,
});

// Mock scrollTo
window.scrollTo = vi.fn();

// Mock fetch for API tests
global.fetch = vi.fn();

// Reset mocks before each test
beforeEach(() => {
  vi.clearAllMocks();
});

// Clean up after all tests
afterAll(() => {
  vi.restoreAllMocks();
});

