import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, useToast } from '../ui';
import { CloseIcon, CopyIcon, QRIcon } from '../Icons';
import { hapticFeedback } from '../../lib/telegram';
import { QRCodeSVG } from 'qrcode.react';
import styles from './DepositAddressModal.module.css';

interface DepositAddressModalProps {
  address: string;
  network: string;
  expectedAmount?: string;
  onClose: () => void;
  onNetworkChange?: (network: string) => void;
}

const NETWORKS = [
  { id: 'erc20', name: 'Ethereum (ERC20)', icon: 'Ξ' },
  { id: 'bep20', name: 'BSC (BEP20)', icon: 'BNB' },
  { id: 'trc20', name: 'Tron (TRC20)', icon: 'T' },
];

export function DepositAddressModal({
  address,
  network,
  expectedAmount,
  onClose,
  onNetworkChange,
}: DepositAddressModalProps) {
  const [selectedNetwork, setSelectedNetwork] = useState(network);
  const [showQR, setShowQR] = useState(true);
  const { showSuccess } = useToast();

  useEffect(() => {
    if (onNetworkChange && selectedNetwork !== network) {
      onNetworkChange(selectedNetwork);
    }
  }, [selectedNetwork, network, onNetworkChange]);

  const handleCopy = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text);
      hapticFeedback('light');
      showSuccess(`${label} copied to clipboard`);
    } catch (err) {
      // Ignore
    }
  };

  const currentNetwork = NETWORKS.find(n => n.id === selectedNetwork) || NETWORKS[0];

  return (
    <div className={styles.overlay} onClick={onClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        <Card variant="elevated">
          <CardHeader>
            <div className={styles.modalHeader}>
              <CardTitle>Deposit Address</CardTitle>
              <button
                className={styles.closeButton}
                onClick={onClose}
                aria-label="Close"
              >
                <CloseIcon size={20} />
              </button>
            </div>
          </CardHeader>
          <CardContent>
            {/* Network Selection */}
            {onNetworkChange && (
              <div className={styles.networkSelection}>
                <label className={styles.label}>Network</label>
                <div className={styles.networkButtons}>
                  {NETWORKS.map((net) => (
                    <button
                      key={net.id}
                      className={`${styles.networkButton} ${selectedNetwork === net.id ? styles.active : ''}`}
                      onClick={() => setSelectedNetwork(net.id)}
                    >
                      <span className={styles.networkIcon}>{net.icon}</span>
                      <span className={styles.networkName}>{net.name}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* QR Code */}
            {showQR && address && (
              <div className={styles.qrSection}>
                <div className={styles.qrContainer}>
                  <QRCodeSVG
                    value={address}
                    size={200}
                    level="M"
                    includeMargin={true}
                    className={styles.qrCode}
                  />
                </div>
                <button
                  className={styles.toggleQR}
                  onClick={() => setShowQR(false)}
                >
                  Hide QR Code
                </button>
              </div>
            )}

            {!showQR && (
              <button
                className={styles.toggleQR}
                onClick={() => setShowQR(true)}
              >
                <QRIcon size={20} />
                Show QR Code
              </button>
            )}

            {/* Address */}
            <div className={styles.addressSection}>
              <label className={styles.label}>Deposit Address</label>
              <div className={styles.addressContainer}>
                <code className={styles.address}>{address}</code>
                <button
                  className={styles.copyButton}
                  onClick={() => handleCopy(address, 'Address')}
                  aria-label="Copy address"
                >
                  <CopyIcon size={18} />
                </button>
              </div>
              <p className={styles.addressHint}>
                Send only {currentNetwork.name} USDT to this address
              </p>
            </div>

            {/* Expected Amount */}
            {expectedAmount && (
              <div className={styles.amountSection}>
                <label className={styles.label}>Expected Amount</label>
                <div className={styles.amountValue}>
                  {expectedAmount} USDT
                </div>
                <p className={styles.amountHint}>
                  Send exactly this amount for automatic processing
                </p>
              </div>
            )}

            {/* Important Notes */}
            <div className={styles.notesSection}>
              <h4 className={styles.notesTitle}>Important Notes</h4>
              <ul className={styles.notesList}>
                <li>Only send USDT on {currentNetwork.name} network</li>
                <li>Do not send other cryptocurrencies</li>
                <li>Deposits are processed automatically after confirmation</li>
                <li>Minimum deposit amount applies</li>
              </ul>
            </div>

            <div className={styles.modalActions}>
              <Button fullWidth onClick={onClose}>
                Close
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

