import React, { createContext, useState, useContext, ReactNode } from 'react';

interface Verification {
  id: number;
  type: 'lottery' | 'airdrop' | 'ai_trader' | 'withdrawal';
  context: any;
  initiatedAt: string;
  status?: 'pending' | 'processing' | 'completed' | 'failed';
  result?: any;
}

interface VerificationHistoryItem {
  id: number;
  type: string;
  status: string;
  amount?: number;
  createdAt: string;
  completedAt?: string;
}

interface VerificationContextType {
  currentVerification: Verification | null;
  verificationHistory: VerificationHistoryItem[];
  isVerificationRequired: (type: string, context: any) => boolean;
  setCurrentVerification: (verification: Verification | null) => void;
  addToHistory: (item: VerificationHistoryItem) => void;
  clearCurrentVerification: () => void;
  getVerificationStatus: (id: number) => Promise<any>;
  getAssistedVerificationStatus: (id: number) => Promise<any>;
}

const VerificationContext = createContext<VerificationContextType | undefined>(undefined);

export const useVerification = () => {
  const context = useContext(VerificationContext);
  if (!context) {
    throw new Error('useVerification must be used within VerificationProvider');
  }
  return context;
};

interface VerificationProviderProps {
  children: ReactNode;
}

export const VerificationProvider: React.FC<VerificationProviderProps> = ({ children }) => {
  const [currentVerification, setCurrentVerification] = useState<Verification | null>(null);
  const [verificationHistory, setVerificationHistory] = useState<VerificationHistoryItem[]>([]);

  const isVerificationRequired = (type: string, context: any): boolean => {
    // Business logic to determine if verification is required
    switch (type) {
      case 'lottery':
        return true; // Always require for lottery prizes

      case 'airdrop':
        const amount = context.amount || 0;
        return amount > 10; // Require for amounts > $10

      case 'ai_trader':
        return true; // Always require for AI Trader withdrawals

      case 'withdrawal':
        return true; // Always require for general withdrawals

      default:
        return false;
    }
  };

  const addToHistory = (item: VerificationHistoryItem) => {
    setVerificationHistory(prev => [item, ...prev.slice(0, 49)]); // Keep last 50 items
  };

  const clearCurrentVerification = () => {
    setCurrentVerification(null);
  };

  const getVerificationStatus = async (id: number): Promise<any> => {
    try {
      const response = await fetch(`/RockyTap/api/verification/session/${id}`);
      const result = await response.json();

      if (result.success) {
        return result.data;
      }

      throw new Error(result.error?.message || 'Failed to get status');
    } catch (error) {
      console.error('Failed to fetch verification status:', error);
      throw error;
    }
  };

  const getAssistedVerificationStatus = async (id: number): Promise<any> => {
    try {
      const response = await fetch(`/RockyTap/api/verification/assisted/status/${id}`);
      const result = await response.json();

      if (result.success || result.ok) {
        return result.data || result;
      }

      throw new Error(result.error?.message || result.message || 'Failed to get assisted verification status');
    } catch (error) {
      console.error('Failed to fetch assisted verification status:', error);
      throw error;
    }
  };

  return (
    <VerificationContext.Provider
      value={{
        currentVerification,
        verificationHistory,
        isVerificationRequired,
        setCurrentVerification,
        addToHistory,
        clearCurrentVerification,
        getVerificationStatus,
        getAssistedVerificationStatus
      }}
    >
      {children}
    </VerificationContext.Provider>
  );
};

