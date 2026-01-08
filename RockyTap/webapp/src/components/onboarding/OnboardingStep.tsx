import { Card, CardContent } from '../ui';
import { TelegramBranding } from '../TelegramBranding';
import { TrustBadgeBar } from '../TrustBadgeBar';
import styles from './OnboardingStep.module.css';

interface OnboardingStepProps {
  step: {
    id: string;
    title: string;
    description: string;
    icon: string;
    image?: string;
    features?: string[];
  };
  stepNumber: number;
  totalSteps: number;
}

export function OnboardingStep({ step, stepNumber, totalSteps }: OnboardingStepProps) {
  return (
    <Card variant="glow" className={styles.stepCard}>
      <CardContent>
        {/* Icon */}
        <div className={styles.iconContainer}>
          <span className={styles.icon}>{step.icon}</span>
        </div>

        {/* Title */}
        <h2 className={styles.title}>{step.title}</h2>

        {/* Description */}
        <p className={styles.description}>{step.description}</p>

        {/* Telegram Branding for welcome/security steps */}
        {(step.id === 'welcome' || step.id === 'security') && (
          <div className={styles.trustSection}>
            <TelegramBranding variant="badge" />
          </div>
        )}

        {/* Trust Badges for security step */}
        {step.id === 'security' && (
          <div className={styles.trustBadges}>
            <TrustBadgeBar variant="compact" showLabels={false} />
          </div>
        )}

        {/* Features List */}
        {step.features && step.features.length > 0 && (
          <ul className={styles.featuresList}>
            {step.features.map((feature, index) => (
              <li key={index} className={styles.feature}>
                <span className={styles.featureIcon}>✓</span>
                <span className={styles.featureText}>{feature}</span>
              </li>
            ))}
          </ul>
        )}

        {/* Step Indicator */}
        <div className={styles.stepIndicator}>
          Step {stepNumber} of {totalSteps}
        </div>
      </CardContent>
    </Card>
  );
}

