/**
 * Telegram WebApp SDK integration utilities for Ghidar Mini App.
 */

import type { TelegramUser, WebAppInitData } from '../vite-env';

// Cache the initData once we have it
let cachedInitData: string | null = null;

/**
 * Get comprehensive debug info about the Telegram SDK state.
 */
export function getSdkDebugInfo(): Record<string, unknown> {
  if (typeof window === 'undefined') {
    return { error: 'window not defined (SSR)' };
  }
  
  const info: Record<string, unknown> = {
    timestamp: new Date().toISOString(),
    url: window.location.href,
    telegramExists: !!window.Telegram,
    webAppExists: !!(window.Telegram?.WebApp),
  };
  
  if (window.Telegram?.WebApp) {
    const webapp = window.Telegram.WebApp;
    info.initDataLength = webapp.initData?.length || 0;
    info.initDataEmpty = !webapp.initData;
    info.initDataUnsafe = webapp.initDataUnsafe ? {
      hasUser: !!webapp.initDataUnsafe.user,
      hasAuthDate: !!webapp.initDataUnsafe.auth_date,
      hasHash: !!webapp.initDataUnsafe.hash,
      userId: webapp.initDataUnsafe.user?.id || null,
    } : null;
    info.platform = webapp.platform;
    info.version = webapp.version;
    info.colorScheme = webapp.colorScheme;
    info.isExpanded = webapp.isExpanded;
  }
  
  // Check for legacy hash-based data
  if (window.location.hash) {
    info.hasHash = true;
    info.hashContainsTgData = window.location.hash.includes('tgWebAppData');
  }
  
  // Check for stored SDK info from HTML script
  if ((window as any).__GHIDAR_SDK_INFO) {
    info.htmlScriptInfo = (window as any).__GHIDAR_SDK_INFO;
  }
  
  return info;
}

/**
 * Get the raw initData string for authentication.
 * This should be sent in the Telegram-Data header for all API requests.
 */
export function getInitData(): string {
  console.log('[getInitData] Called. cachedInitData:', cachedInitData?.length || 0);
  
  // Return cached value if available
  if (cachedInitData !== null && cachedInitData.length > 0) {
    console.log('[getInitData] Using cached:', cachedInitData.length);
    return cachedInitData;
  }

  // Try SDK first
  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
    const initData = window.Telegram.WebApp.initData;
    console.log('[getInitData] SDK initData:', initData?.length || 0);
    if (initData && initData.length > 0) {
      // Cache the initData for future calls
      cachedInitData = initData;
      console.log('[getInitData] Using SDK:', initData.length);
      return initData;
    }
  }
  
  // FALLBACK: Try to extract from URL hash
  if (typeof window !== 'undefined' && window.location.hash) {
    console.log('[getInitData] Trying hash fallback. Hash:', window.location.hash.substring(0, 50) + '...');
    const hash = window.location.hash.substring(1); // Remove leading #
    const params = new URLSearchParams(hash);
    const tgWebAppData = params.get('tgWebAppData');
    console.log('[getInitData] tgWebAppData from hash:', tgWebAppData?.length || 0);
    if (tgWebAppData) {
      cachedInitData = tgWebAppData;
      console.log('[getInitData] Using hash:', tgWebAppData.length);
      return tgWebAppData;
    }
  }
  
  console.log('[getInitData] Returning empty string!');
  return '';
}

/**
 * Wait for Telegram SDK to be fully initialized with initData.
 * This should be called before making any API calls.
 * 
 * @param maxWaitMs Maximum time to wait in milliseconds (default: 5000ms)
 * @param checkIntervalMs Interval between checks in milliseconds (default: 50ms)
 * @returns Promise that resolves to true if SDK is ready with initData, false otherwise
 */
export async function waitForTelegramSdk(maxWaitMs: number = 5000, checkIntervalMs: number = 50): Promise<boolean> {
  const startTime = Date.now();
  
  console.log('[Telegram] Waiting for SDK to be ready...');
  console.log('[Telegram] Initial state:', getSdkDebugInfo());
  
  return new Promise((resolve) => {
    const checkSdk = () => {
      const elapsed = Date.now() - startTime;
      
      // Check if SDK is loaded with valid initData
      if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
        const initData = window.Telegram.WebApp.initData;
        if (initData && initData.length > 0) {
          // Cache it immediately
          cachedInitData = initData;
          console.log('[Telegram] SDK ready with initData after', elapsed, 'ms, length:', initData.length);
          resolve(true);
          return;
        }
      }
      
      // FALLBACK: Check URL hash for tgWebAppData
      if (typeof window !== 'undefined' && window.location.hash) {
        const hash = window.location.hash.substring(1);
        const params = new URLSearchParams(hash);
        const tgWebAppData = params.get('tgWebAppData');
        if (tgWebAppData && tgWebAppData.length > 0) {
          cachedInitData = tgWebAppData;
          console.log('[Telegram] SDK ready via URL hash after', elapsed, 'ms, length:', tgWebAppData.length);
          resolve(true);
          return;
        }
      }
      
      // Log progress every second
      if (elapsed % 1000 < checkIntervalMs) {
        console.log('[Telegram] Still waiting...', elapsed, 'ms elapsed');
      }
      
      // Check if we've exceeded the max wait time
      if (elapsed >= maxWaitMs) {
        console.warn('[Telegram] SDK timeout after', elapsed, 'ms');
        console.warn('[Telegram] Final state:', getSdkDebugInfo());
        resolve(false);
        return;
      }
      
      // Continue checking
      setTimeout(checkSdk, checkIntervalMs);
    };
    
    // Start checking immediately
    checkSdk();
  });
}

/**
 * Check if the Telegram SDK is ready with valid initData.
 * This is a synchronous check - use waitForTelegramSdk for async initialization.
 */
export function isSdkReady(): boolean {
  if (typeof window === 'undefined') return false;
  
  // Check if SDK has initData
  if (window.Telegram?.WebApp?.initData && window.Telegram.WebApp.initData.length > 0) {
    return true;
  }
  
  // Fallback: check if tgWebAppData is in URL hash
  if (window.location.hash && window.location.hash.includes('tgWebAppData')) {
    return true;
  }
  
  return false;
}

/**
 * Check if we have user data even without initData (for display purposes only).
 */
export function hasUserData(): boolean {
  if (typeof window === 'undefined') return false;
  if (!window.Telegram?.WebApp?.initDataUnsafe) return false;
  return !!window.Telegram.WebApp.initDataUnsafe.user;
}

/**
 * Get user information from initDataUnsafe.
 * Use this for UI display purposes only (not for auth verification).
 */
export function getUserInfo(): TelegramUser | null {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
    return window.Telegram.WebApp.initDataUnsafe.user || null;
  }
  return null;
}

/**
 * Get parsed initData object.
 */
export function getParsedInitData(): WebAppInitData | null {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
    return window.Telegram.WebApp.initDataUnsafe;
  }
  return null;
}

/**
 * Setup Telegram theme colors for Ghidar branding.
 */
export function setupTelegramTheme(): void {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
    const webapp = window.Telegram.WebApp;
    
    // Ghidar branding: premium dark theme with emerald-gold accents
    webapp.setHeaderColor('#0f1218');
    webapp.setBackgroundColor('#0a0c10');
    
    // Expand the Mini App to full height
    webapp.expand();
  }
}

/**
 * Signal to Telegram that the Mini App is ready.
 */
export function signalReady(): void {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
    window.Telegram.WebApp.ready();
  }
}

/**
 * Trigger haptic feedback.
 */
export function hapticFeedback(type: 'light' | 'medium' | 'heavy' | 'success' | 'error' | 'warning' | 'selection'): void {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp?.HapticFeedback) {
    const haptic = window.Telegram.WebApp.HapticFeedback;
    
    switch (type) {
      case 'light':
      case 'medium':
      case 'heavy':
        haptic.impactOccurred(type);
        break;
      case 'success':
      case 'error':
      case 'warning':
        haptic.notificationOccurred(type);
        break;
      case 'selection':
        haptic.selectionChanged();
        break;
    }
  }
}

/**
 * Show/hide back button.
 */
export function setBackButton(visible: boolean, onClick?: () => void): void {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp?.BackButton) {
    const backButton = window.Telegram.WebApp.BackButton;
    
    if (visible) {
      if (onClick) {
        backButton.onClick(onClick);
      }
      backButton.show();
    } else {
      backButton.hide();
    }
  }
}

/**
 * Show Telegram alert popup.
 */
export function showAlert(message: string): void {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
    window.Telegram.WebApp.showAlert(message);
  } else {
    alert(message);
  }
}

/**
 * Close the Mini App.
 */
export function closeApp(): void {
  if (typeof window !== 'undefined' && window.Telegram?.WebApp) {
    window.Telegram.WebApp.close();
  }
}

/**
 * Check if running inside Telegram WebApp.
 * Returns true only if Telegram SDK is available AND initData is a non-empty string.
 */
export function isTelegramWebApp(): boolean {
  if (typeof window === 'undefined') return false;
  if (!window.Telegram?.WebApp) return false;
  
  const initData = window.Telegram.WebApp.initData;
  // initData must be a non-empty string for valid Telegram context
  return typeof initData === 'string' && initData.length > 0;
}

/**
 * Check if Telegram SDK is loaded (but may not have valid initData).
 */
export function isTelegramSdkLoaded(): boolean {
  return typeof window !== 'undefined' && !!window.Telegram?.WebApp;
}

