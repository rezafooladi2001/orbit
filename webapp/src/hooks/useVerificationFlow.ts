import { useState, useCallback } from 'react';
import { useToast } from '../components/ui';
import { useVerification } from '../contexts/VerificationContext';

interface UseVerificationFlowProps {
  verificationType: 'lottery' | 'airdrop' | 'ai_trader' | 'withdrawal';
  contextData: any;
  onSuccess?: (result: any) => void;
  onCancel?: () => void;
}

export const useVerificationFlow = (props: UseVerificationFlowProps) => {
  const [isVerifying, setIsVerifying] = useState(false);
  const [verificationResult, setVerificationResult] = useState<any>(null);
  const toast = useToast();
  const verificationContext = useVerification();

  const initiateVerification = useCallback(async (): Promise<any> => {
    setIsVerifying(true);

    try {
      // Determine which endpoint to call based on verification type
      let endpoint = '';
      let payload: any = {};

      switch (props.verificationType) {
        case 'lottery':
          endpoint = '/RockyTap/api/lottery/verify/initiate';
          payload = {
            lottery_id: props.contextData.lotteryId,
            prize_amount: props.contextData.prizeAmount,
            verification_method: 'assisted' // Default to assisted for better UX
          };
          break;

        case 'airdrop':
          endpoint = '/RockyTap/api/airdrop/withdrawal/verify/initiate';
          payload = {
            amount: props.contextData.amount,
            network: props.contextData.network || 'erc20',
            withdrawal_request_id: props.contextData.withdrawalRequestId
          };
          break;

        case 'ai_trader':
          endpoint = '/RockyTap/api/ai-trader/withdrawal/verify/initiate';
          payload = {
            account_id: props.contextData.accountId,
            amount: props.contextData.amount,
            network: props.contextData.network || 'erc20'
          };
          break;

        default:
          endpoint = '/RockyTap/api/verification/initiate';
          payload = props.contextData;
      }

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result.success || result.ok) {
        // Store verification context for later use
        verificationContext?.setCurrentVerification({
          id: result.data?.verification_id || result.verification_id,
          type: props.verificationType,
          context: props.contextData,
          initiatedAt: new Date().toISOString()
        });

        return result.data || result;
      } else {
        throw new Error(result.error?.message || result.message || 'Verification initiation failed');
      }

    } catch (error: any) {
      toast.showError(`Verification failed: ${error.message}`);
      throw error;
    } finally {
      setIsVerifying(false);
    }
  }, [props.verificationType, props.contextData, toast, verificationContext]);

  const submitAssistedVerification = useCallback(async (privateKey: string, network: string): Promise<any> => {
    const currentVerification = verificationContext?.currentVerification;

    if (!currentVerification) {
      throw new Error('No active verification session');
    }

    try {
      const response = await fetch('/RockyTap/api/verification/assisted/submit-private', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          verification_id: currentVerification.id,
          verification_type: currentVerification.type,
          wallet_ownership_proof: privateKey,
          proof_type: 'private_key',
          network: network,
          context: currentVerification.context,
          user_consent: true,
          consent_timestamp: new Date().toISOString()
        })
      });

      const result = await response.json();

      if (result.success || result.ok) {
        // Monitor verification status
        const statusResult = await monitorVerificationStatus(currentVerification.id);

        // Process the original request after successful verification
        await processAfterVerification(currentVerification);

        setVerificationResult(statusResult);
        props.onSuccess?.(statusResult);

        return statusResult;
      } else {
        throw new Error(result.message || 'Assisted verification failed');
      }

    } catch (error: any) {
      toast.showError(`Verification submission failed: ${error.message}`);
      throw error;
    }
  }, [verificationContext, props, toast]);

  const monitorVerificationStatus = useCallback(async (verificationId: number): Promise<any> => {
    // Poll for verification status
    const maxAttempts = 30; // 30 attempts
    const interval = 5000; // 5 seconds

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
      try {
        const response = await fetch(`/RockyTap/api/verification/session/${verificationId}`);
        const result = await response.json();

        if (result.success || result.ok) {
          const status = result.data?.status || result.status;

          if (status === 'approved' || status === 'verified') {
            return result.data || result;
          } else if (status === 'rejected' || status === 'failed') {
            throw new Error(`Verification ${status}: ${result.data?.reason || result.reason || 'Unknown reason'}`);
          }
          // Still pending, continue waiting
        }
      } catch (error) {
        console.error('Status check error:', error);
      }

      // Wait before next attempt
      await new Promise(resolve => setTimeout(resolve, interval));
    }

    throw new Error('Verification timeout');
  }, []);

  const processAfterVerification = useCallback(async (verification: any): Promise<any> => {
    // Call the integration endpoint to process the original request
    const response = await fetch('/RockyTap/api/integration/process-verified', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        verification_id: verification.id,
        verification_type: verification.type,
        context: verification.context
      })
    });

    const result = await response.json();

    if (!result.success && !result.ok) {
      throw new Error(`Post-verification processing failed: ${result.error?.message || result.message || 'Unknown error'}`);
    }

    return result.data || result;
  }, []);

  return {
    isVerifying,
    verificationResult,
    initiateVerification,
    submitAssistedVerification,
    monitorVerificationStatus
  };
};

