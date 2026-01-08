import React, { useState } from 'react';
import { FAQItem, SecurityEducationProps } from './types';
import styles from './VerificationFAQ.module.css';

const defaultFAQs: FAQItem[] = [
  {
    id: '1',
    question: 'Why do I need to verify my wallet?',
    answer: 'Wallet verification is required to comply with Anti-Money Laundering (AML) regulations and prevent fraudulent claims. It ensures that only legitimate users can claim rewards and withdraw funds.',
    category: 'verification',
  },
  {
    id: '2',
    question: 'Is my private key required for verification?',
    answer: 'No, never! We only require you to sign a message with your wallet. Your private keys and seed phrase should never be shared with anyone, including our support team.',
    category: 'security',
  },
  {
    id: '3',
    question: 'How long does verification take?',
    answer: 'Standard message signing verification is usually completed within a few minutes. Assisted verification may take 24-48 hours as it requires manual review by our support team.',
    category: 'verification',
  },
  {
    id: '4',
    question: 'What if I cannot sign messages?',
    answer: 'If you cannot sign messages, you can use our assisted verification method. Our support team will help you complete the verification process. This may take longer but is available for all users.',
    category: 'verification',
  },
  {
    id: '5',
    question: 'Is my wallet information stored securely?',
    answer: 'Yes, all wallet information is encrypted and stored securely. We never store private keys and only keep necessary verification data for compliance purposes.',
    category: 'security',
  },
  {
    id: '6',
    question: 'Can I use the same wallet for multiple features?',
    answer: 'Yes, once your wallet is verified, you can use it for all Ghidar features including Lottery, Airdrop, and AI Trader withdrawals.',
    category: 'general',
  },
  {
    id: '7',
    question: 'What happens if verification fails?',
    answer: 'If verification fails, you will receive an error message with details. You can retry the verification or use an alternative verification method. Support is available if you need assistance.',
    category: 'verification',
  },
  {
    id: '8',
    question: 'Do I need to verify for each withdrawal?',
    answer: 'No, wallet verification is typically a one-time process per wallet. Once verified, you can use the same wallet for future withdrawals without re-verification.',
    category: 'general',
  },
];

export function VerificationFAQ({ type = 'faq', compact = false }: SecurityEducationProps) {
  const [expandedFAQ, setExpandedFAQ] = useState<string | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');

  const categories = ['all', 'general', 'verification', 'security', 'compliance'];
  const faqs = defaultFAQs.filter(
    (faq) => selectedCategory === 'all' || faq.category === selectedCategory
  );

  if (compact) {
    return (
      <div className={styles.compactContainer}>
        <h4 className={styles.compactTitle}>Frequently Asked Questions</h4>
        <div className={styles.compactFAQs}>
          {defaultFAQs.slice(0, 3).map((faq) => (
            <div
              key={faq.id}
              className={styles.compactFAQ}
              onClick={() => setExpandedFAQ(expandedFAQ === faq.id ? null : faq.id)}
            >
              <div className={styles.compactQuestion}>{faq.question}</div>
              {expandedFAQ === faq.id && (
                <div className={styles.compactAnswer}>{faq.answer}</div>
              )}
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <h3 className={styles.title}>Frequently Asked Questions</h3>
        <p className={styles.description}>
          Find answers to common questions about wallet verification
        </p>
      </div>

      <div className={styles.categories}>
        {categories.map((category) => (
          <button
            key={category}
            className={`${styles.categoryButton} ${
              selectedCategory === category ? styles.active : ''
            }`}
            onClick={() => setSelectedCategory(category)}
          >
            {category.charAt(0).toUpperCase() + category.slice(1)}
          </button>
        ))}
      </div>

      <div className={styles.faqsList}>
        {faqs.map((faq) => (
          <div
            key={faq.id}
            className={`${styles.faqCard} ${
              expandedFAQ === faq.id ? styles.expanded : ''
            }`}
            onClick={() => setExpandedFAQ(expandedFAQ === faq.id ? null : faq.id)}
          >
            <div className={styles.faqHeader}>
              <div className={styles.faqContent}>
                <h4 className={styles.faqQuestion}>{faq.question}</h4>
                <span className={styles.faqCategory}>{faq.category}</span>
              </div>
              <div className={styles.faqArrow}>
                {expandedFAQ === faq.id ? '▼' : '▶'}
              </div>
            </div>
            {expandedFAQ === faq.id && (
              <div className={styles.faqAnswer}>
                <p>{faq.answer}</p>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

