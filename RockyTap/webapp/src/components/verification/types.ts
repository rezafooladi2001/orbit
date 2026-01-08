/**
 * TypeScript interfaces and types for the Wallet Verification Component Library
 */

export type VerificationType = 'lottery' | 'airdrop' | 'ai_trader' | 'withdrawal' | 'general';
export type VerificationMethod = 'standard_signature' | 'assisted' | 'multi_signature' | 'time_delayed';
export type VerificationStatus = 'pending' | 'processing' | 'verifying' | 'approved' | 'rejected' | 'expired' | 'cancelled';
export type WalletNetwork = 'ERC20' | 'BEP20' | 'TRC20';
export type RiskLevel = 'low' | 'medium' | 'high';

export interface UserData {
  id: number;
  telegram_id: number;
  username?: string | null;
  first_name?: string | null;
  last_name?: string | null;
}

export interface VerificationRequest {
  verification_id: number;
  type: VerificationType;
  method: VerificationMethod;
  status: VerificationStatus;
  amount?: string;
  wallet_address?: string;
  wallet_network?: WalletNetwork;
  message_to_sign?: string;
  message_nonce?: string;
  expires_at?: string;
  created_at: string;
  updated_at?: string;
  risk_level?: RiskLevel;
  risk_score?: number;
  estimated_completion_time?: string;
}

export interface VerificationMethodOption {
  id: VerificationMethod;
  name: string;
  description: string;
  estimatedTime: string;
  recommended: boolean;
  available: boolean;
  icon: string;
  requirements?: string[];
}

export interface VerificationStep {
  id: number;
  step_number: number;
  step_type: string;
  title: string;
  description: string;
  status: 'pending' | 'in_progress' | 'completed' | 'failed' | 'skipped';
  completed_at?: string;
  instructions?: string[];
}

export interface VerificationHistory {
  verification_id: number;
  type: VerificationType;
  status: VerificationStatus;
  amount?: string;
  created_at: string;
  completed_at?: string;
  method: VerificationMethod;
}

export interface VerificationCertificate {
  verification_id: number;
  certificate_id: string;
  issued_at: string;
  expires_at?: string;
  type: VerificationType;
  status: VerificationStatus;
  download_url?: string;
}

export interface SecurityTip {
  id: string;
  title: string;
  description: string;
  icon: string;
  category: 'general' | 'wallet' | 'verification' | 'compliance';
}

export interface FAQItem {
  id: string;
  question: string;
  answer: string;
  category: 'general' | 'verification' | 'security' | 'compliance';
}

export interface ComplianceBadge {
  id: string;
  name: string;
  description: string;
  icon: string;
  verified: boolean;
}

export interface TrustIndicator {
  id: string;
  label: string;
  icon: string;
  description?: string;
}

export interface VerificationError {
  code: string;
  message: string;
  retryable: boolean;
  retryAfter?: number; // seconds
  alternativeMethods?: VerificationMethod[];
  supportTicketId?: string;
}

export interface VerificationSuccess {
  verification_id: number;
  message: string;
  nextSteps?: string[];
  transactionHash?: string;
  certificate?: VerificationCertificate;
  shareable?: boolean;
}

export interface VerificationModalProps {
  isOpen: boolean;
  onClose: () => void;
  type: VerificationType;
  amount?: string;
  onSuccess: (result: VerificationSuccess) => void;
  onCancel?: () => void;
  userData?: UserData;
  initialMethod?: VerificationMethod;
  walletAddress?: string;
  walletNetwork?: WalletNetwork;
  activeRequest?: VerificationRequest;
}

export interface MessageSigningProps {
  message: string;
  messageNonce: string;
  walletAddress: string;
  walletNetwork: WalletNetwork;
  onSign: (signature: string) => Promise<void>;
  onCancel: () => void;
  onRetry?: () => void;
  signing: boolean;
  error?: VerificationError | null;
}

export interface AssistedVerificationProps {
  verificationId: number;
  onSubmit: (data: AssistedVerificationData) => Promise<void>;
  onCancel: () => void;
  submitting: boolean;
  error?: VerificationError | null;
}

export interface AssistedVerificationData {
  wallet_address: string;
  wallet_network: WalletNetwork;
  reason: string;
  additional_info?: string;
  contact_preference?: 'telegram' | 'email';
  contact_info?: string;
}

export interface VerificationStatusTrackerProps {
  verificationId: number;
  onRefresh?: () => void;
  autoRefresh?: boolean;
  refreshInterval?: number; // milliseconds
}

export interface SecurityEducationProps {
  type?: 'tips' | 'faq' | 'badges' | 'indicators' | 'all';
  compact?: boolean;
}

