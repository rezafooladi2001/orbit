Stack

Node.js (LTS) with TypeScript and strict mode enabled.

Use:

ethers for Ethereum/BSC (ERC20/BEP20 USDT).

Appropriate library for Tron (e.g. tronweb).

Organize code into:

/src/config

/src/services

/src/watchers

/src/api (if needed)

Behavior

This service is responsible for:

Generating deposit addresses per user/network/purpose (if using HD wallet or a configured provider).

Monitoring blockchain transactions to those addresses.

On confirmation:

Calling the PHP backend via HTTP (e.g. POST /api/blockchain/depositCallback) with:

user_id, network, tx_hash, amount, product_type.

Rules

Always validate:

Minimum number of confirmations before calling the backend.

That the token is USDT, not native coin.

Log all blockchain events with enough details for debugging.

Never expose private keys in logs or error messages.