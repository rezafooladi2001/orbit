1) Airdrop / GHD

Treat GHD as an internal token, not a blockchain token (for now).

Keep GHD amounts in wallet.ghd_balance.

Conversion rule: use a single source of truth constant (e.g. GHD_PER_USDT = 1000).

When performing GHD → USDT conversion:

Check:

User has enough GHD.

Amount is above minimum withdrawal threshold if applicable.

Update balances in a single transaction.

2) Lottery

Every lottery must have:

ticket_price_usdt

prize_pool_usdt

start_at, end_at

When issuing tickets:

Derive ticket_count = floor(amount / ticket_price_usdt).

When drawing winners:

Use a clear, reproducible random method (e.g., PHP’s random_int over ticket indices).

Store winners in lottery_winners and adjust wallet balances accordingly.

Never modify historical lotteries; only add new records.

3) AI Trader

ai_accounts.current_balance_usdt must always be consistent with deposits + withdrawals + PnL updates.

Any external AI/trading system must interact only via clear APIs:

Example: POST /api/ai_trader/updatePerformance.

Withdrawals should:

Decrease current_balance_usdt.

Create a row in withdrawals with status = pending.

Never “fake” performance internally; always keep the numbers consistent and explainable.