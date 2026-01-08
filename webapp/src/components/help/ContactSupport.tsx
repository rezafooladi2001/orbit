import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, useToast } from '../ui';
import { hapticFeedback } from '../../lib/telegram';
import { createSupportTicket } from '../../api/client';
import { getFriendlyErrorMessage } from '../../lib/errorMessages';
import styles from './ContactSupport.module.css';

interface ContactSupportProps {
  onBack: () => void;
}

export function ContactSupport({ onBack }: ContactSupportProps) {
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const { showSuccess, showError } = useToast();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!subject.trim() || !message.trim()) {
      showError('Please fill in all fields');
      return;
    }

    try {
      setSubmitting(true);
      hapticFeedback('light');
      
      await createSupportTicket({
        subject: subject.trim(),
        message: message.trim(),
      });

      hapticFeedback('success');
      showSuccess('Support ticket created successfully! We will get back to you soon.');
      
      // Reset form
      setSubject('');
      setMessage('');
      
      // Go back after a delay
      setTimeout(() => {
        onBack();
      }, 2000);
    } catch (err) {
      hapticFeedback('error');
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showError(errorMessage);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className={styles.container}>
      <Card variant="elevated">
        <CardHeader>
          <CardTitle>Contact Support</CardTitle>
        </CardHeader>
        <CardContent>
          <p className={styles.description}>
            Fill out the form below and our support team will get back to you as soon as possible.
          </p>

          <form onSubmit={handleSubmit} className={styles.form}>
            <Input
              label="Subject"
              placeholder="What can we help you with?"
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              required
              disabled={submitting}
            />

            <div className={styles.textareaWrapper}>
              <label className={styles.label}>Message</label>
              <textarea
                className={styles.textarea}
                placeholder="Describe your issue or question in detail..."
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                required
                disabled={submitting}
                rows={8}
              />
            </div>

            <div className={styles.formActions}>
              <Button
                variant="secondary"
                onClick={onBack}
                disabled={submitting}
              >
                Cancel
              </Button>
              <Button
                type="submit"
                loading={submitting}
                disabled={submitting}
              >
                Submit Ticket
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <div className={styles.info}>
            <h4 className={styles.infoTitle}>Response Time</h4>
            <p className={styles.infoText}>
              We typically respond within 24-48 hours. For urgent matters, please mention it in your message.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

