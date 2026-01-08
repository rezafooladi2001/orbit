# Ghidar Backend

Telegram Mini App backend for Ghidar (Airdrop Clicker, Lottery, AI Trader). Originally based on Rocky clicker backend.

## Overview

Ghidar is a Telegram-based clicker game backend that provides:
- User authentication via Telegram WebApp
- Clicker game mechanics (tapping, energy, balance)
- Mission and task system
- Referral system
- Admin panel for management

Future modules (Airdrop, Lottery, AI Trader) will be implemented in later development phases.

## Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Telegram Bot Token

## Installation

1. **Clone or extract the project**

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   ```
   
   Edit `.env` and set the following:
   - `APP_ENV` - Environment (local, staging, production)
   - `APP_URL` - Your application URL
   - `DB_HOST` - Database host (usually localhost)
   - `DB_PORT` - Database port (usually 3306)
   - `DB_DATABASE` - Database name
   - `DB_USERNAME` - Database username
   - `DB_PASSWORD` - Database password
   - `TELEGRAM_BOT_TOKEN` - Your Telegram bot token
   - `TELEGRAM_BOT_USERNAME` - Your Telegram bot username
   - `ADMINS_USER_ID` - Comma-separated list of admin Telegram user IDs

4. **Set up database**
   ```bash
   php RockyTap/database/create_tables.php
   ```

5. **Configure web server**
   
   Point your web server document root to the `RockyTap` directory, or configure virtual host accordingly.

## Project Structure

```
.
├── bootstrap.php              # Application bootstrap (loads autoloader, config)
├── composer.json              # Composer dependencies and autoloading
├── .env.example               # Environment variables template
├── src/                       # PSR-4 namespaced source code
│   ├── Config/
│   │   └── Config.php        # Configuration manager
│   ├── Core/
│   │   ├── Database.php      # PDO database connection
│   │   └── Response.php      # JSON response helpers
│   └── Telegram/
│       └── BotClient.php     # Telegram Bot API wrapper
└── RockyTap/                  # Main application directory
    ├── index.php             # Frontend entry point
    ├── bot/
    │   ├── index.php         # Telegram bot webhook handler
    │   ├── config.php        # Legacy config (uses Config class)
    │   └── functions.php     # Helper functions
    ├── api/                  # API endpoints
    │   ├── login/           # User authentication
    │   ├── getUser/         # Get user data
    │   ├── tap/             # Handle taps
    │   └── ...              # Other endpoints
    └── database/
        └── create_tables.php # Database schema
```

## Entry Points

- **Bot Webhook**: `RockyTap/bot/index.php` - Handles Telegram bot updates
- **Frontend**: `RockyTap/index.php` - Main frontend HTML
- **API Base**: `RockyTap/api/` - REST API endpoints

## Development

### Running Locally

You can use PHP's built-in server for development:

```bash
cd RockyTap
php -S localhost:8000
```

Then access the application at `http://localhost:8000`

### Code Standards

- PHP 8.1+ with strict types
- PSR-12 coding style
- PSR-4 autoloading
- All configuration via environment variables
- Parameterized queries for database access

### Namespace Structure

- `Ghidar\Config` - Configuration management
- `Ghidar\Core` - Core utilities (Database, Response)
- `Ghidar\Telegram` - Telegram API integration

Future namespaces will include:
- `Ghidar\Airdrop` - Airdrop module (to be implemented)
- `Ghidar\Lottery` - Lottery module (to be implemented)
- `Ghidar\AITrader` - AI Trader module (to be implemented)

## Configuration

All configuration is managed through environment variables in `.env`. The `Ghidar\Config\Config` class provides access to these values.

Example:
```php
use Ghidar\Config\Config;

$botToken = Config::get('TELEGRAM_BOT_TOKEN');
$dbHost = Config::get('DB_HOST', 'localhost'); // with default value
```

## Database

The application uses MySQL/MariaDB. Database connection is managed through:
- Legacy: `mysqli` (existing code)
- New: `Ghidar\Core\Database` (PDO) - for future migrations

**Note**: Database table names have not been changed from the original Rocky implementation. This will be addressed in future refactoring phases.

## Security Notes

- Never commit `.env` file to version control
- Use parameterized queries (existing code may need migration)
- Validate all Telegram WebApp data
- Keep dependencies updated via `composer update`

## Migration from Rocky

This codebase has been refactored from the original Rocky clicker backend:
- Rebranded to Ghidar
- Introduced PSR-4 autoloading
- Centralized configuration via environment variables
- Added core utility classes
- Maintained backward compatibility with existing database and business logic

## Logging

The application uses a centralized logging system for important business events and errors.

### Log Location

Logs are written to: `RockyTap/storage/logs/ghidar.log`

The log directory is created automatically on first use.

### Log Format

Each log entry is a JSON-encoded line with the following structure:

```json
{
  "timestamp": "2024-01-15 10:30:45",
  "level": "info",
  "message": "Business event: deposit_confirmed",
  "action": "deposit_confirmed",
  "user_id": 12345,
  "context": {
    "deposit_id": 789,
    "amount_usdt": "100.00000000",
    "network": "trc20"
  }
}
```

### Log Levels

- **info**: General informational messages and business events
- **warning**: Warning conditions (e.g., duplicate processing attempts, validation failures)
- **error**: Error conditions (e.g., exceptions, failures)

### Logged Events

The following business events are logged:

- **Deposits**: `deposit_confirmed`, `deposit_double_process_or_invalid`, `deposit_confirmed_failed`
- **Withdrawals**: `withdrawal_requested`, `withdrawal_rejected`
- **Airdrop**: `airdrop_convert`, `airdrop_convert_failed`
- **Lottery**: `lottery_purchase`, `lottery_draw`, `lottery_draw_again_attempt`
- **AI Trader**: `ai_trader_deposit`, `ai_trader_withdraw`, `ai_trader_operation_failed`
- **Referral**: `referral_reward_issued`, `referral_reward_duplicate_skipped`

### Viewing Logs

To view logs in real-time:

```bash
tail -f RockyTap/storage/logs/ghidar.log
```

To view recent logs:

```bash
tail -n 100 RockyTap/storage/logs/ghidar.log
```

## Healthcheck

A healthcheck endpoint is available for infrastructure monitoring.

### Endpoint

`GET /RockyTap/api/health/`

### Response

**Success (200 OK):**
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "db": "ok",
    "php_version": "8.1.27"
  },
  "error": null
}
```

**Failure (500 Internal Server Error):**
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "HEALTHCHECK_FAILED",
    "message": "Healthcheck failed: database connection error"
  }
}
```

### Checks Performed

- Database connectivity (executes `SELECT 1`)
- PHP version (informational)

Healthcheck failures are logged to the application log.

## Tests & CI

### Running Tests Locally

1. Install dependencies:
   ```bash
   composer install
   ```

2. Run PHPUnit tests:
   ```bash
   vendor/bin/phpunit
   ```

### CI Pipeline

The project includes a GitHub Actions CI workflow (`.github/workflows/ci.yml`) that runs on:
- Push to `main` branch
- Pull requests to `main` branch

The CI pipeline performs:

1. **Backend Tests**:
   - Sets up PHP 8.1 with required extensions
   - Installs Composer dependencies
   - Runs PHPUnit test suite

2. **Frontend Build**:
   - Sets up Node.js 20
   - Installs npm dependencies
   - Builds the frontend application

The CI workflow ensures code quality and prevents broken builds from being merged.

## Features

The application includes the following implemented features:

- **Airdrop (GHD)**: Token airdrop system with tapping mechanics
- **Lottery**: Lottery system with ticket purchases and prize distribution
- **AI Trader**: AI-powered trading features with deposit/withdrawal
- **Wallet Verification**: Secure wallet verification system with cross-chain recovery
- **Referral System**: Multi-level referral rewards
- **Admin Panel**: Comprehensive admin dashboard for user and system management
- **Security**: Hardened security with encryption, authentication, and input validation

## Security

The application has undergone comprehensive security hardening:

- All SQL queries use prepared statements to prevent SQL injection
- Strong encryption using PBKDF2 with 100,000 iterations
- Admin authentication middleware for protected endpoints
- Input validation and sanitization throughout
- CSRF protection for state-changing operations
- Secure key management via ComplianceKeyVault

For production deployment, ensure:
- All environment variables are properly configured
- SSL/TLS certificates are installed
- Database credentials are secure
- Admin user IDs are correctly set in `ADMINS_USER_ID`

## Deployment

### Production Deployment Checklist

1. **System Requirements**:
   - PHP 8.1+ with extensions: `pdo`, `pdo_mysql`, `json`, `curl`, `openssl`, `mbstring`
   - MySQL 8.0+ or MariaDB 10.5+ (utf8mb4 support)
   - Node.js 18+ (for blockchain-service)
   - SSL certificate (required for Telegram WebApp)

2. **Installation**:
   ```bash
   # Install PHP dependencies
   composer install --no-dev --optimize-autoloader
   
   # Install blockchain service dependencies
   cd blockchain-service
   npm install
   npm run build
   ```

3. **Database Setup**:
   ```bash
   php RockyTap/database/create_tables.php
   ```

4. **Environment Configuration**:
   - Copy `.env.example` to `.env`
   - Configure all required environment variables
   - Set `APP_ENV=production`

5. **Web Server Configuration**:
   - Point document root to `RockyTap` directory
   - Configure SSL/TLS
   - Set up proper file permissions

6. **Blockchain Service**:
   - Configure RPC endpoints in blockchain-service `.env`
   - Start the service: `npm start` or use PM2/Supervisor

### Health Check

Monitor application health via:
```
GET /RockyTap/api/health/
```

## Architecture

The application consists of three main components:

1. **PHP Backend** (`src/`, `RockyTap/api/`): Handles business logic, API endpoints, and database operations
2. **Blockchain Service** (`blockchain-service/`): Monitors blockchain transactions and processes deposits
3. **React Mini App** (`RockyTap/webapp/`): Telegram WebApp frontend built with React and TypeScript

## License

[Add your license information here]

## Support

[Add support information here]

