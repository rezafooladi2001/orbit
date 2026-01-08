export function formatTransactionType(type: string): string {
  const types: Record<string, string> = {
    deposit: 'Deposit',
    withdrawal: 'Withdrawal',
    conversion: 'Conversion',
    lottery: 'Lottery',
    ai_trader: 'AI Trader',
    referral: 'Referral Reward',
  };
  return types[type] || type;
}

export function formatTransactionStatus(status: string): string {
  const statuses: Record<string, string> = {
    completed: 'Completed',
    pending: 'Pending',
    failed: 'Failed',
    processing: 'Processing',
  };
  return statuses[status] || status;
}

export function formatAmount(amount: string, currency: string = 'USDT'): string {
  const num = parseFloat(amount);
  const sign = num >= 0 ? '+' : '';
  const formatted = Math.abs(num).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 8,
  });
  return `${sign}${formatted} ${currency}`;
}

export function formatDate(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;

  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
  });
}

