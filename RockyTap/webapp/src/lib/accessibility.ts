/**
 * Accessibility Utilities for Ghidar Mini-App
 * Provides helpers for keyboard navigation, focus management, and screen reader support
 */

/**
 * Trap focus within a container element (useful for modals)
 */
export function trapFocus(container: HTMLElement): () => void {
  const focusableElements = container.querySelectorAll<HTMLElement>(
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  );
  
  const firstElement = focusableElements[0];
  const lastElement = focusableElements[focusableElements.length - 1];
  
  const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key !== 'Tab') return;
    
    if (e.shiftKey) {
      // Shift + Tab: going backwards
      if (document.activeElement === firstElement) {
        e.preventDefault();
        lastElement.focus();
      }
    } else {
      // Tab: going forward
      if (document.activeElement === lastElement) {
        e.preventDefault();
        firstElement.focus();
      }
    }
  };
  
  container.addEventListener('keydown', handleKeyDown);
  
  // Focus the first element
  if (firstElement) {
    firstElement.focus();
  }
  
  // Return cleanup function
  return () => {
    container.removeEventListener('keydown', handleKeyDown);
  };
}

/**
 * Save and restore focus (useful for modals)
 */
export function saveFocus(): () => void {
  const previouslyFocused = document.activeElement as HTMLElement;
  
  return () => {
    if (previouslyFocused && previouslyFocused.focus) {
      previouslyFocused.focus();
    }
  };
}

/**
 * Announce message to screen readers
 */
export function announceToScreenReader(
  message: string,
  priority: 'polite' | 'assertive' = 'polite'
): void {
  const liveRegion = document.getElementById('sr-live-region') || createLiveRegion();
  liveRegion.setAttribute('aria-live', priority);
  
  // Clear and set message (forces re-announcement)
  liveRegion.textContent = '';
  requestAnimationFrame(() => {
    liveRegion.textContent = message;
  });
}

function createLiveRegion(): HTMLElement {
  const region = document.createElement('div');
  region.id = 'sr-live-region';
  region.setAttribute('aria-live', 'polite');
  region.setAttribute('aria-atomic', 'true');
  region.className = 'sr-only';
  region.style.cssText = `
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  `;
  document.body.appendChild(region);
  return region;
}

/**
 * Handle keyboard navigation for lists and grids
 */
export interface KeyboardNavigationOptions {
  /** Element to attach listeners to */
  container: HTMLElement;
  /** Selector for navigable items */
  itemSelector: string;
  /** Orientation of the list/grid */
  orientation: 'vertical' | 'horizontal' | 'grid';
  /** Number of columns (for grid orientation) */
  columns?: number;
  /** Enable wrap-around navigation */
  wrap?: boolean;
  /** Callback when item is selected */
  onSelect?: (item: HTMLElement, index: number) => void;
  /** Callback when focus changes */
  onFocusChange?: (item: HTMLElement, index: number) => void;
}

export function setupKeyboardNavigation(options: KeyboardNavigationOptions): () => void {
  const {
    container,
    itemSelector,
    orientation,
    columns = 1,
    wrap = true,
    onSelect,
    onFocusChange,
  } = options;

  const getItems = (): HTMLElement[] => {
    return Array.from(container.querySelectorAll<HTMLElement>(itemSelector));
  };

  const getCurrentIndex = (): number => {
    const items = getItems();
    return items.findIndex(item => item === document.activeElement || item.contains(document.activeElement));
  };

  const focusItem = (index: number): void => {
    const items = getItems();
    if (items[index]) {
      items[index].focus();
      onFocusChange?.(items[index], index);
    }
  };

  const handleKeyDown = (e: KeyboardEvent): void => {
    const items = getItems();
    const currentIndex = getCurrentIndex();
    
    if (currentIndex === -1) return;
    
    let nextIndex = currentIndex;
    let handled = false;
    
    switch (e.key) {
      case 'ArrowUp':
        if (orientation === 'vertical' || orientation === 'grid') {
          nextIndex = orientation === 'grid' 
            ? currentIndex - columns 
            : currentIndex - 1;
          handled = true;
        }
        break;
        
      case 'ArrowDown':
        if (orientation === 'vertical' || orientation === 'grid') {
          nextIndex = orientation === 'grid' 
            ? currentIndex + columns 
            : currentIndex + 1;
          handled = true;
        }
        break;
        
      case 'ArrowLeft':
        if (orientation === 'horizontal' || orientation === 'grid') {
          nextIndex = currentIndex - 1;
          handled = true;
        }
        break;
        
      case 'ArrowRight':
        if (orientation === 'horizontal' || orientation === 'grid') {
          nextIndex = currentIndex + 1;
          handled = true;
        }
        break;
        
      case 'Home':
        nextIndex = 0;
        handled = true;
        break;
        
      case 'End':
        nextIndex = items.length - 1;
        handled = true;
        break;
        
      case 'Enter':
      case ' ':
        if (onSelect) {
          onSelect(items[currentIndex], currentIndex);
          handled = true;
        }
        break;
    }
    
    if (handled) {
      e.preventDefault();
      
      // Handle wrapping
      if (wrap) {
        if (nextIndex < 0) {
          nextIndex = items.length - 1;
        } else if (nextIndex >= items.length) {
          nextIndex = 0;
        }
      } else {
        nextIndex = Math.max(0, Math.min(items.length - 1, nextIndex));
      }
      
      if (nextIndex !== currentIndex) {
        focusItem(nextIndex);
      }
    }
  };

  container.addEventListener('keydown', handleKeyDown);
  
  return () => {
    container.removeEventListener('keydown', handleKeyDown);
  };
}

/**
 * Check if user prefers reduced motion
 */
export function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Check if user prefers high contrast
 */
export function prefersHighContrast(): boolean {
  return window.matchMedia('(prefers-contrast: more)').matches;
}

/**
 * Generate unique ID for accessibility attributes
 */
let idCounter = 0;
export function generateA11yId(prefix: string = 'a11y'): string {
  return `${prefix}-${++idCounter}`;
}

/**
 * Skip link component setup (call in App.tsx)
 */
export function setupSkipLink(): void {
  // Check if skip link already exists
  if (document.getElementById('skip-link')) return;
  
  const skipLink = document.createElement('a');
  skipLink.id = 'skip-link';
  skipLink.href = '#main-content';
  skipLink.textContent = 'Skip to main content';
  skipLink.className = 'skip-link';
  skipLink.style.cssText = `
    position: absolute;
    left: -9999px;
    top: 0;
    z-index: 10000;
    padding: 8px 16px;
    background: var(--primary, #10b981);
    color: white;
    text-decoration: none;
    border-radius: 0 0 4px 0;
  `;
  
  skipLink.addEventListener('focus', () => {
    skipLink.style.left = '0';
  });
  
  skipLink.addEventListener('blur', () => {
    skipLink.style.left = '-9999px';
  });
  
  document.body.insertBefore(skipLink, document.body.firstChild);
}

/**
 * Format number for screen readers (adds thousands separators)
 */
export function formatNumberForSR(num: number): string {
  return num.toLocaleString('en-US');
}

/**
 * Format currency for screen readers
 */
export function formatCurrencyForSR(amount: number, currency: string = 'USD'): string {
  return `${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })} ${currency}`;
}

/**
 * Format date for screen readers
 */
export function formatDateForSR(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  return d.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format time for screen readers
 */
export function formatTimeForSR(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  return d.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: 'numeric',
    hour12: true,
  });
}

