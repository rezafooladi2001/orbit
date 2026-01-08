import React, { useState } from 'react';
import { VerificationSuccess } from './types';
import { Button } from '../ui';
import { useToast } from '../ui';
import { hapticFeedback } from '../../lib/telegram';
import styles from './VerificationSuccessState.module.css';

interface VerificationSuccessStateProps {
  success: VerificationSuccess;
  onNext?: () => void;
  onDownloadCertificate?: (certificateId: string) => void;
  onShare?: () => void;
}

export function VerificationSuccessState({
  success,
  onNext,
  onDownloadCertificate,
  onShare,
}: VerificationSuccessStateProps) {
  const [showAnimation, setShowAnimation] = useState(true);
  const { showSuccess } = useToast();

  React.useEffect(() => {
    hapticFeedback('success');
    showSuccess('Verification successful!');
    
    // Hide animation after 2 seconds
    const timer = setTimeout(() => {
      setShowAnimation(false);
    }, 2000);
    
    return () => clearTimeout(timer);
  }, []);

  const handleDownloadCertificate = () => {
    if (success.certificate && onDownloadCertificate) {
      onDownloadCertificate(success.certificate.certificate_id);
    }
  };

  const handleShare = () => {
    if (onShare) {
      onShare();
    } else if (navigator.share) {
      navigator.share({
        title: 'Wallet Verification Successful',
        text: `I've successfully verified my wallet on Ghidar! Verification ID: ${success.verification_id}`,
      }).catch(() => {
        // Share failed, silently ignore
      });
    }
  };

  return (
    <div className={styles.container}>
      {showAnimation && (
        <div className={styles.animationContainer}>
          <div className={styles.successIcon}>✓</div>
          <div className={styles.confetti}>
            {[...Array(20)].map((_, i) => (
              <div
                key={i}
                className={styles.confettiPiece}
                style={{
                  left: `${Math.random() * 100}%`,
                  animationDelay: `${Math.random() * 0.5}s`,
                  backgroundColor: ['#10b981', '#fbbf24', '#3b82f6', '#ef4444'][
                    Math.floor(Math.random() * 4)
                  ],
                }}
              />
            ))}
          </div>
        </div>
      )}

      <div className={styles.content}>
        <div className={styles.successHeader}>
          <div className={styles.successIconStatic}>✓</div>
          <h3 className={styles.title}>Verification Successful!</h3>
          <p className={styles.message}>{success.message}</p>
        </div>

        <div className={styles.verificationInfo}>
          <div className={styles.infoItem}>
            <span className={styles.infoLabel}>Verification ID:</span>
            <span className={styles.infoValue}>#{success.verification_id}</span>
          </div>
          {success.transactionHash && (
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Transaction:</span>
              <a
                href={`https://etherscan.io/tx/${success.transactionHash}`}
                target="_blank"
                rel="noopener noreferrer"
                className={styles.transactionLink}
              >
                View on Explorer
              </a>
            </div>
          )}
        </div>

        {success.nextSteps && success.nextSteps.length > 0 && (
          <div className={styles.nextSteps}>
            <h4 className={styles.nextStepsTitle}>Next Steps</h4>
            <ul className={styles.nextStepsList}>
              {success.nextSteps.map((step, index) => (
                <li key={index}>{step}</li>
              ))}
            </ul>
          </div>
        )}

        {success.certificate && (
          <div className={styles.certificateSection}>
            <div className={styles.certificateInfo}>
              <span className={styles.certificateIcon}>📜</span>
              <div className={styles.certificateContent}>
                <strong>Verification Certificate</strong>
                <p>Certificate ID: {success.certificate.certificate_id}</p>
                <p className={styles.certificateNote}>
                  Issued: {new Date(success.certificate.issued_at).toLocaleDateString()}
                </p>
              </div>
            </div>
            {onDownloadCertificate && (
              <Button
                variant="outline"
                size="md"
                onClick={handleDownloadCertificate}
              >
                Download Certificate
              </Button>
            )}
          </div>
        )}

        <div className={styles.actions}>
          {success.shareable && onShare && (
            <Button
              variant="secondary"
              size="lg"
              onClick={handleShare}
              fullWidth
            >
              Share Success
            </Button>
          )}
          {onNext && (
            <Button
              variant="gold"
              size="lg"
              onClick={onNext}
              fullWidth
            >
              Continue
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}

