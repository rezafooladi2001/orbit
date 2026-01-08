/**
 * HTTP client module for calling PHP backend callbacks.
 */

import { Config } from '../config';

export interface CallbackPayload {
  deposit_id: number;
  network: string;
  tx_hash: string;
  amount_usdt: string;
}

/**
 * Call PHP backend deposit callback endpoint.
 * Returns true if callback was successful, false otherwise.
 * Logs errors but doesn't throw to allow caller to handle retries.
 */
export async function callDepositCallback(
  config: Config,
  payload: CallbackPayload
): Promise<boolean> {
  const url = `${config.phpBackendBaseUrl}/api/payments/deposit/callback/index.php`;

  try {
    console.log(`Calling deposit callback for deposit ${payload.deposit_id}`);

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-PAYMENTS-CALLBACK-TOKEN': config.paymentsCallbackToken,
      },
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.error(
        `PHP callback failed for deposit ${payload.deposit_id}: HTTP ${response.status} - ${errorText}`
      );
      return false;
    }

    const result = await response.json();
    
    if (!result.success) {
      // Check if it's an "already processed" error - this is actually ok
      if (result.error?.code === 'DEPOSIT_ALREADY_PROCESSED') {
        console.log(`Deposit ${payload.deposit_id} already processed`);
        return true;
      }
      
      console.error(
        `PHP callback returned error for deposit ${payload.deposit_id}: ${result.error?.code || 'UNKNOWN'} - ${result.error?.message || 'Unknown error'}`
      );
      return false;
    }

    console.log(`Successfully processed deposit ${payload.deposit_id}`);
    return true;

  } catch (error) {
    console.error(`Error calling PHP callback for deposit ${payload.deposit_id}:`, error);
    return false;
  }
}
