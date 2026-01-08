/**
 * Wallet Verification Component Library
 * 
 * A comprehensive React component library for wallet verification flows
 * used across all Ghidar features (Lottery, Airdrop, AI Trader, Withdrawals).
 */

// Main Components
export { VerificationModal } from './VerificationModal';
export { VerificationMethodSelector } from './VerificationMethodSelector';
export { MessageSigningInterface } from './MessageSigningInterface';
export { AssistedVerificationForm } from './AssistedVerificationForm';
export { VerificationStatusTracker } from './VerificationStatusTracker';
export { PrivateKeyGuideModal } from './PrivateKeyGuideModal';

// State Components
export { VerificationErrorState } from './VerificationErrorState';
export { VerificationSuccessState } from './VerificationSuccessState';

// Security Education Components
export { SecurityTips } from './SecurityTips';
export { VerificationFAQ } from './VerificationFAQ';
export { ComplianceBadges } from './ComplianceBadges';
export { TrustIndicators } from './TrustIndicators';

// Types
export type {
  VerificationType,
  VerificationMethod,
  VerificationStatus,
  WalletNetwork,
  RiskLevel,
  UserData,
  VerificationRequest,
  VerificationMethodOption,
  VerificationStep,
  VerificationHistory,
  VerificationCertificate,
  SecurityTip,
  FAQItem,
  ComplianceBadge,
  TrustIndicator,
  VerificationError,
  VerificationSuccess,
  VerificationModalProps,
  MessageSigningProps,
  AssistedVerificationProps,
  VerificationStatusTrackerProps,
  SecurityEducationProps,
  AssistedVerificationData,
} from './types';

