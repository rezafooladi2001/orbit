import { useState, useEffect } from 'react';

const ONBOARDING_COMPLETE_KEY = 'ghidar_onboarding_complete';
const ONBOARDING_VERSION = '1.0';

export function useOnboarding() {
  const [showOnboarding, setShowOnboarding] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check if onboarding has been completed
    const checkOnboarding = () => {
      try {
        const completed = localStorage.getItem(ONBOARDING_COMPLETE_KEY);
        const version = localStorage.getItem(`${ONBOARDING_COMPLETE_KEY}_version`);
        
        // If version changed, show onboarding again
        if (version !== ONBOARDING_VERSION) {
          setShowOnboarding(true);
          setIsLoading(false);
          return;
        }

        setShowOnboarding(!completed);
      } catch (err) {
        // If localStorage fails, show onboarding
        setShowOnboarding(true);
      } finally {
        setIsLoading(false);
      }
    };

    checkOnboarding();
  }, []);

  const completeOnboarding = () => {
    try {
      localStorage.setItem(ONBOARDING_COMPLETE_KEY, 'true');
      localStorage.setItem(`${ONBOARDING_COMPLETE_KEY}_version`, ONBOARDING_VERSION);
      setShowOnboarding(false);
    } catch (err) {
      // Ignore localStorage errors
      setShowOnboarding(false);
    }
  };

  const resetOnboarding = () => {
    try {
      localStorage.removeItem(ONBOARDING_COMPLETE_KEY);
      localStorage.removeItem(`${ONBOARDING_COMPLETE_KEY}_version`);
      setShowOnboarding(true);
    } catch (err) {
      // Ignore localStorage errors
      setShowOnboarding(true);
    }
  };

  return {
    showOnboarding,
    isLoading,
    completeOnboarding,
    resetOnboarding,
  };
}

