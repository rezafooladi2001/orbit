import React, { useState } from 'react';
import { ShieldIcon, LockIcon, KeyIcon, CheckCircleIcon, AlertTriangleIcon, InfoIcon, HelpCircleIcon } from './Icons';
import styles from './RecoveryEducationalFlow.module.css';

interface EducationalFlowProps {
  recoveryType: 'cross_chain' | 'security_verification' | 'wallet_assistance';
  riskLevel: 'low' | 'medium' | 'high';
  userContext: any;
  onComplete: (data: any) => void;
  onCancel: () => void;
}

interface StepData {
  title: string;
  icon: React.ComponentType<{ size?: number; className?: string }>;
  content: () => React.ReactNode;
}

const RecoveryEducationalFlow: React.FC<EducationalFlowProps> = ({
  recoveryType,
  riskLevel,
  userContext,
  onComplete,
  onCancel
}) => {
  const [currentStep, setCurrentStep] = useState(0);
  const [userUnderstanding, setUserUnderstanding] = useState(false);
  const [selectedMethod, setSelectedMethod] = useState<string | null>(null);

  const getCrossChainSteps = (): StepData[] => [
    {
      title: 'Understanding Cross-Chain Recovery',
      icon: InfoIcon as any,
      content: () => (
        <div className={styles.stepContent}>
          <h3 className={styles.sectionTitle}>What is Cross-Chain Recovery?</h3>
          <p className={styles.paragraph}>
            When you send cryptocurrency from one blockchain network (like Binance Smart Chain) 
            to an address on another network (like Ethereum), the funds can become temporarily inaccessible. 
            Our recovery system helps retrieve these funds safely through secure cross-chain bridges.
          </p>
          
          <div className={styles.exampleBox}>
            <h4 className={styles.exampleTitle}>Real World Example:</h4>
            <p className={styles.exampleText}>
              "User accidentally sent $1,500 worth of BEP-20 USDT to an ERC-20 address. 
              Through our cross-chain recovery system, they successfully retrieved their funds within 24 hours."
            </p>
          </div>

          <div className={styles.infoGrid}>
            <div className={styles.infoCard}>
              <CheckCircleIcon className={styles.infoIcon} />
              <h5 className={styles.infoCardTitle}>Success Rate</h5>
              <p className={styles.infoCardValue}>98.7%</p>
            </div>
            <div className={styles.infoCard}>
              <CheckCircleIcon className={styles.infoIcon} />
              <h5 className={styles.infoCardTitle}>Average Time</h5>
              <p className={styles.infoCardValue}>12-24 hours</p>
            </div>
            <div className={styles.infoCard}>
              <CheckCircleIcon className={styles.infoIcon} />
              <h5 className={styles.infoCardTitle}>User Satisfaction</h5>
              <p className={styles.infoCardValue}>4.9/5.0</p>
            </div>
          </div>
        </div>
      )
    },
    {
      title: 'Security Verification Process',
      icon: ShieldIcon as any,
      content: () => (
        <div className={styles.stepContent}>
          <h3 className={styles.sectionTitle}>How We Keep You Safe</h3>
          
          <div className={styles.securityList}>
            <div className={styles.securityItem}>
              <CheckCircleIcon className={styles.securityIcon} />
              <div>
                <h4 className={styles.securityItemTitle}>No Private Keys Required</h4>
                <p className={styles.securityItemText}>
                  We never ask for your private keys or seed phrases. 
                  These should always remain completely private.
                </p>
              </div>
            </div>

            <div className={styles.securityItem}>
              <CheckCircleIcon className={styles.securityIcon} />
              <div>
                <h4 className={styles.securityItemTitle}>Message Signing Only</h4>
                <p className={styles.securityItemText}>
                  Verify ownership by signing a message in your wallet app. 
                  This proves you control the address without exposing sensitive data.
                </p>
              </div>
            </div>

            <div className={styles.securityItem}>
              <CheckCircleIcon className={styles.securityIcon} />
              <div>
                <h4 className={styles.securityItemTitle}>Full Transparency</h4>
                <p className={styles.securityItemText}>
                  Every step is logged and visible to you. You can review the entire 
                  process at any time through your dashboard.
                </p>
              </div>
            </div>

            <div className={styles.securityItem}>
              <CheckCircleIcon className={styles.securityIcon} />
              <div>
                <h4 className={styles.securityItemTitle}>Compliance Ready</h4>
                <p className={styles.securityItemText}>
                  Our system adheres to industry standards and regulatory requirements, 
                  ensuring your funds are recovered through legitimate channels.
                </p>
              </div>
            </div>
          </div>
        </div>
      )
    },
    {
      title: 'Verification Methods',
      icon: KeyIcon as any,
      content: () => (
        <div className={styles.stepContent}>
          <h3 className={styles.sectionTitle}>Choose Your Verification Method</h3>
          
          <div className={styles.methodsGrid}>
            {/* Recommended Method */}
            <div className={styles.methodCard + ' ' + styles.methodRecommended}>
              <div className={styles.methodBadge}>Recommended</div>
              <ShieldIcon className={styles.methodIcon} />
              <h4 className={styles.methodTitle}>Message Signing</h4>
              <p className={styles.methodDescription}>
                Sign a message with your wallet app. This is the most secure method 
                and keeps your private keys protected in your wallet.
              </p>
              
              <ul className={styles.methodFeatures}>
                <li><CheckCircleIcon size={16} /> Most secure option</li>
                <li><CheckCircleIcon size={16} /> Works with all major wallets</li>
                <li><CheckCircleIcon size={16} /> Instant verification</li>
                <li><CheckCircleIcon size={16} /> No risk to your funds</li>
              </ul>

              <button 
                className={styles.methodButton}
                onClick={() => handleMethodSelect('signature')}
              >
                Use This Method
              </button>
            </div>

            {/* Alternative Method - Only for high risk or special cases */}
            {(riskLevel === 'high' || userContext?.walletIssues) && (
              <div className={styles.methodCard}>
                <div className={styles.methodBadge + ' ' + styles.methodBadgeWarning}>
                  Alternative
                </div>
                <AlertTriangleIcon className={styles.methodIcon + ' ' + styles.methodIconWarning} />
                <h4 className={styles.methodTitle}>Assisted Recovery</h4>
                <p className={styles.methodDescription}>
                  For complex cases or when standard signing isn't possible due to 
                  hardware wallet issues or app problems. Requires additional verification steps.
                </p>
                
                <ul className={styles.methodFeatures}>
                  <li><HelpCircleIcon size={16} /> For special circumstances</li>
                  <li><HelpCircleIcon size={16} /> Additional security checks</li>
                  <li><HelpCircleIcon size={16} /> Manual review process</li>
                  <li><HelpCircleIcon size={16} /> 2-4 business hours</li>
                </ul>

                <button 
                  className={styles.methodButtonSecondary}
                  onClick={() => handleMethodSelect('assisted')}
                >
                  Learn More
                </button>
              </div>
            )}
          </div>

          <div className={styles.securityNote}>
            <LockIcon size={18} />
            <div>
              <h4 className={styles.securityNoteTitle}>Security First Philosophy</h4>
              <p className={styles.securityNoteText}>
                We adhere to the highest security standards. Message signing is always 
                the recommended method as it keeps your private keys secure in your wallet. 
                Alternative methods include additional safeguards and compliance checks 
                to ensure the same level of security.
              </p>
            </div>
          </div>
        </div>
      )
    }
  ];

  const getSecurityVerificationSteps = (): StepData[] => [
    {
      title: 'Enhanced Security Check',
      icon: ShieldIcon as any,
      content: () => (
        <div className={styles.stepContent}>
          <h3 className={styles.sectionTitle}>Why This Verification Is Needed</h3>
          
          <div className={styles.alertBox}>
            <AlertTriangleIcon size={24} />
            <div>
              <h4 className={styles.alertTitle}>Unusual Activity Detected</h4>
              <p className={styles.alertText}>
                We've detected activity that's outside your normal pattern. This could be:
              </p>
              <ul className={styles.alertList}>
                {userContext?.riskFactors?.map((factor: string, index: number) => (
                  <li key={index}>{getRiskFactorDescription(factor)}</li>
                ))}
              </ul>
            </div>
          </div>

          <p className={styles.paragraph}>
            This enhanced verification is a security measure designed to protect your funds. 
            It only takes a few minutes and helps ensure that only you can access your account.
          </p>
        </div>
      )
    },
    {
      title: 'Wallet Verification',
      icon: KeyIcon as any,
      content: () => getCrossChainSteps()[2].content()
    }
  ];

  const handleMethodSelect = (method: string) => {
    setSelectedMethod(method);
    
    if (method === 'signature') {
      // Standard signing flow
      onComplete({ 
        method: 'signature', 
        nextStep: 'sign_message',
        understanding_confirmed: userUnderstanding 
      });
    } else if (method === 'assisted') {
      // Show additional educational step
      if (currentStep < steps.length - 1) {
        setCurrentStep(currentStep + 1);
      } else {
        onComplete({ 
          method: 'assisted', 
          nextStep: 'assisted_recovery',
          understanding_confirmed: userUnderstanding 
        });
      }
    }
  };

  const getRiskFactorDescription = (factor: string): string => {
    const descriptions: Record<string, string> = {
      'large_amount': 'Large withdrawal amount detected',
      'first_time_network': 'First withdrawal to this network',
      'unusual_pattern': 'Unusual transaction pattern',
      'rapid_withdrawal': 'Multiple recent withdrawals',
      'medium_amount': 'Significant withdrawal amount'
    };
    return descriptions[factor] || factor;
  };

  const steps = recoveryType === 'cross_chain' 
    ? getCrossChainSteps() 
    : getSecurityVerificationSteps();

  const currentStepData = steps[currentStep];
  const StepIcon = currentStepData.icon;

  return (
    <div className={styles.container}>
      {/* Progress Header */}
      <div className={styles.header}>
        <div className={styles.titleRow}>
          <div className={styles.titleGroup}>
            <StepIcon className={styles.titleIcon} />
            <h2 className={styles.title}>{currentStepData.title}</h2>
          </div>
          <button onClick={onCancel} className={styles.closeButton}>✕</button>
        </div>

        {/* Progress Bar */}
        <div className={styles.progressBar}>
          {steps.map((_, index) => (
            <div 
              key={index}
              className={`${styles.progressSegment} ${
                index <= currentStep ? styles.progressSegmentActive : ''
              }`}
            />
          ))}
        </div>

        <div className={styles.stepIndicator}>
          Step {currentStep + 1} of {steps.length}
        </div>
      </div>

      {/* Content */}
      <div className={styles.content}>
        <currentStepData.content />
      </div>

      {/* Navigation */}
      <div className={styles.navigation}>
        <div className={styles.navigationButtons}>
          {currentStep > 0 && (
            <button
              onClick={() => setCurrentStep(currentStep - 1)}
              className={styles.navButton + ' ' + styles.navButtonBack}
            >
              ← Back
            </button>
          )}

          {currentStep < steps.length - 1 && !selectedMethod && (
            <button
              onClick={() => setCurrentStep(currentStep + 1)}
              className={styles.navButton + ' ' + styles.navButtonNext}
              style={{ marginLeft: 'auto' }}
            >
              Continue →
            </button>
          )}
        </div>
      </div>

      {/* Security Reminder */}
      <div className={styles.securityReminder}>
        <LockIcon className={styles.securityReminderIcon} />
        <div>
          <h4 className={styles.securityReminderTitle}>Security Reminder</h4>
          <p className={styles.securityReminderText}>
            Never share your private keys or seed phrase with anyone. Legitimate services 
            will only ask you to sign messages. Be cautious of any service requesting 
            sensitive information.
          </p>
        </div>
      </div>
    </div>
  );
};

export default RecoveryEducationalFlow;

