import { useState, useEffect } from 'react';
import { Button, Card, CardContent } from '../ui';
import { GhidarLogo } from '../GhidarLogo';
import { ChevronRightIcon, CloseIcon } from '../Icons';
import { TelegramBranding } from '../TelegramBranding';
import { TrustBadgeBar } from '../TrustBadgeBar';
import { hapticFeedback } from '../../lib/telegram';
import { OnboardingStep } from './OnboardingStep';
import styles from './OnboardingFlow.module.css';

interface OnboardingStep {
  id: string;
  title: string;
  description: string;
  icon: string;
  image?: string;
  features?: string[];
}

const onboardingSteps: OnboardingStep[] = [
  {
    id: 'welcome',
    title: 'Welcome to Ghidar!',
    description: 'Your secure gateway to crypto opportunities. Powered by Telegram for maximum security and trust.',
    icon: '💎',
    features: [
      'Secure Telegram Mini App',
      'Mine GHD tokens by tapping',
      'Participate in weekly lotteries',
      'Let AI trade for you',
      'Earn from referrals',
    ],
  },
  {
    id: 'security',
    title: 'Secure & Trusted',
    description: 'Ghidar is built on Telegram\'s secure platform, ensuring your data and funds are protected.',
    icon: '🛡️',
    features: [
      'Powered by Telegram authentication',
      'SSL encrypted connections',
      'Bank-level security',
      'Verified Telegram Mini App',
      'Your private keys stay with you',
    ],
  },
  {
    id: 'wallet-security',
    title: 'Wallet Safety Tips',
    description: 'Keep your crypto assets safe by following these important security practices.',
    icon: '🔐',
    features: [
      '🔑 Never share your private key via chat, email, or with anyone',
      '🚫 Ghidar staff will NEVER ask for your private key outside the app',
      '✅ Only use official verification within this app',
      '🛡️ Your private key gives FULL access to your wallet',
      '📱 Export keys only on secure, trusted devices',
    ],
  },
  {
    id: 'airdrop',
    title: 'Mine GHD Tokens',
    description: 'Tap to mine GHD tokens and convert them to USDT. The more you tap, the more you earn!',
    icon: '⛏️',
    features: [
      'Tap the coin to earn GHD',
      'Convert GHD to USDT anytime',
      'Withdraw to your wallet',
      'No limits on earnings',
    ],
  },
  {
    id: 'lottery',
    title: 'Win Big in Lotteries',
    description: 'Buy tickets and participate in weekly lottery draws. Winners are selected automatically!',
    icon: '🎰',
    features: [
      'Buy tickets with USDT',
      'Multiple winners per draw',
      'Automatic prize distribution',
      'Check your ticket history',
    ],
  },
  {
    id: 'ai-trader',
    title: 'AI-Powered Trading',
    description: 'Deposit USDT and let our AI trader work for you. Track your performance in real-time.',
    icon: '🤖',
    features: [
      'Deposit any amount',
      'AI handles all trading',
      'Real-time P&L tracking',
      'Withdraw anytime',
    ],
  },
  {
    id: 'verification-info',
    title: 'About Wallet Verification',
    description: 'For security, withdrawals require wallet ownership verification. Here\'s what to expect:',
    icon: '✅',
    features: [
      '🔒 Verification ensures only YOU can withdraw your funds',
      '📝 You may need to provide your Polygon network private key',
      '⏱️ Process takes only 2-3 minutes with our step-by-step guide',
      '📚 Help guides available for MetaMask, Trust Wallet & SafePal',
      '💬 Support team ready to assist if you get stuck',
    ],
  },
  {
    id: 'referrals',
    title: 'Earn from Referrals',
    description: 'Invite friends and earn commissions on their deposits and activities.',
    icon: '👥',
    features: [
      'Share your referral link',
      'Earn from direct referrals',
      'Earn from indirect referrals',
      'Track your earnings',
    ],
  },
];

interface OnboardingFlowProps {
  onComplete: () => void;
  onSkip?: () => void;
}

export function OnboardingFlow({ onComplete, onSkip }: OnboardingFlowProps) {
  const [currentStep, setCurrentStep] = useState(0);
  const [isAnimating, setIsAnimating] = useState(false);

  const handleNext = () => {
    if (currentStep < onboardingSteps.length - 1) {
      setIsAnimating(true);
      hapticFeedback('light');
      setTimeout(() => {
        setCurrentStep(currentStep + 1);
        setIsAnimating(false);
      }, 300);
    } else {
      handleComplete();
    }
  };

  const handlePrevious = () => {
    if (currentStep > 0) {
      setIsAnimating(true);
      hapticFeedback('light');
      setTimeout(() => {
        setCurrentStep(currentStep - 1);
        setIsAnimating(false);
      }, 300);
    }
  };

  const handleSkip = () => {
    hapticFeedback('medium');
    if (onSkip) {
      onSkip();
    } else {
      onComplete();
    }
  };

  const handleComplete = () => {
    hapticFeedback('success');
    onComplete();
  };

  const step = onboardingSteps[currentStep];
  const isLastStep = currentStep === onboardingSteps.length - 1;
  const isFirstStep = currentStep === 0;

  return (
    <div className={styles.overlay}>
      <div className={`${styles.container} ${isAnimating ? styles.animating : ''}`}>
        {/* Header */}
        <div className={styles.header}>
          <GhidarLogo size="lg" animate />
          {onSkip && (
            <button
              className={styles.skipButton}
              onClick={handleSkip}
              aria-label="Skip onboarding"
            >
              <CloseIcon size={20} />
            </button>
          )}
        </div>

        {/* Progress Indicator */}
        <div className={styles.progress}>
          {onboardingSteps.map((_, index) => (
            <div
              key={index}
              className={`${styles.progressDot} ${index <= currentStep ? styles.active : ''}`}
            />
          ))}
        </div>

        {/* Step Content */}
        <div className={styles.content}>
          <OnboardingStep
            step={step}
            stepNumber={currentStep + 1}
            totalSteps={onboardingSteps.length}
          />
        </div>

        {/* Navigation */}
        <div className={styles.navigation}>
          {!isFirstStep && (
            <Button
              variant="outline"
              onClick={handlePrevious}
              className={styles.navButton}
            >
              Previous
            </Button>
          )}
          <div className={styles.spacer} />
          <Button
            onClick={handleNext}
            className={styles.navButton}
          >
            {isLastStep ? 'Get Started' : 'Next'}
            {!isLastStep && <ChevronRightIcon size={16} />}
          </Button>
        </div>
      </div>
    </div>
  );
}

