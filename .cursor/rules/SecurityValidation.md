Always validate user input:

IDs must be integers.

Amounts must be positive decimals and within reasonable limits.

For all money/amount logic:

Use DECIMAL, not float.

Validate currency is always USDT where required.

Implement rate limiting or basic throttling on sensitive endpoints:

/api/airdrop/tap

/api/lottery/initiateOrder

/api/wallet/withdraw

Never expose internal IDs or secrets in client-side logs or error messages.