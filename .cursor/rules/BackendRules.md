Stack & Style

PHP version: 8.1+. Use strict typing whenever possible.

Follow PSR-12 coding style and PSR-4 autoloading.

Use declare(strict_types=1); at the top of PHP files where possible.

Use namespaces for new code (e.g. Ghidar\Core, Ghidar\Lottery, Ghidar\Airdrop, Ghidar\AITrader).

Prefer OOP structure for new modules, even if legacy Rocky code is procedural.

Database

Use MySQL with InnoDB and UTF-8 (utf8mb4).

When creating tables:

Always include id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY.

Include timestamps: created_at, updated_at (and confirmed_at or processed_at if relevant).

Use appropriate numeric types: DECIMAL(32, 8) for monetary amounts.

Never build SQL via string concatenation with user input.

Always use prepared statements / parameter binding.

API Design

All new APIs should be JSON over HTTP.

Structure:

URLs like /api/v1/....

Responses should be of the form:

{
  "success": true,
  "data": { ... },
  "error": null
}


or

{
  "success": false,
  "data": null,
  "error": {
    "code": "SOME_ERROR_CODE",
    "message": "Human readable error"
  }
}


Do not output raw HTML from API endpoints.

All API endpoints must:

Validate inputs.

Authenticate the user via Telegram initData or session token.

Return consistent error structures.

Telegram Integration

For login:

Always validate Telegram WebApp initData using the Bot Token (HMAC check).

Never trust telegram_id sent directly by the client without validation.

For the Telegram Bot (webhook):

Keep index.php small. Extract logic to separate classes/functions.

Add clear handlers for:

/start

admin commands (/admin, etc.)

When sending messages from backend events (e.g. lottery win), wrap Telegram API calls in a single helper/service.

Configuration & Secrets

All secrets (DB password, bot token, RPC URLs, private keys) must be loaded from:

.env file or environment variables.

Never hard-code secrets in source code.

Do not log secrets or full raw initData.