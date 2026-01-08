# Ghidar API Documentation

## Overview

This document describes the REST API endpoints for the Ghidar Telegram Mini-App.

### Base URL

```
Production: https://your-domain.com/RockyTap/api
Development: http://localhost/RockyTap/api
```

### Authentication

All API requests must include the Telegram WebApp `initData` in the request headers:

```
Telegram-Data: <initData string>
```

The server validates:
- Hash verification using HMAC-SHA256
- `auth_date` must be within 24 hours (replay attack prevention)
- User extraction from the `user` field

### Response Format

All API responses follow this structure:

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

Error responses:

```json
{
  "success": false,
  "error": "ERROR_CODE",
  "message": "Human-readable error message"
}
```

### Rate Limiting

- Default: 60 requests per minute per user
- Tap endpoint: 120 requests per minute
- Withdrawal endpoints: 10 requests per minute

Rate limit headers:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining in window
- `X-RateLimit-Reset`: Unix timestamp when limit resets

---

## Endpoints

### User Management

#### GET /getUser

Get current user's data including balances, stats, and profile.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123456789,
    "first_name": "John",
    "last_name": "Doe",
    "username": "johndoe",
    "language_code": "en",
    "is_premium": 1,
    "balance": "1250.50",
    "usdt_balance": "25.00",
    "wallet_address": "0x...",
    "wallet_network": "ERC20",
    "wallet_verified": true,
    "joining_date": 1704067200,
    "referral_code": "ABC123",
    "referral_count": 5,
    "total_taps": 15000,
    "streak_days": 7
  }
}
```

---

### Game Mechanics

#### POST /tap

Record user tap action and update balance.

**Request Body:**
```json
{
  "count": 1,
  "timestamp": 1704067200000
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "new_balance": "1251.50",
    "tap_reward": "1.00",
    "bonus_applied": false,
    "energy_remaining": 95
  }
}
```

---

### Wallet Verification

#### POST /wallet-verification/create

Initiate wallet verification process.

**Request Body:**
```json
{
  "type": "withdrawal",
  "method": "standard_signature",
  "amount": "10.00",
  "wallet_address": "0x...",
  "wallet_network": "ERC20"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "verification_id": 12345,
    "type": "withdrawal",
    "method": "standard_signature",
    "status": "pending",
    "message_to_sign": "Ghidar Wallet Verification\n\nVerification ID: 12345\nType: withdrawal\nTimestamp: 1704067200",
    "message_nonce": "abc123",
    "expires_at": "2024-01-01T12:00:00Z"
  }
}
```

#### POST /wallet-verification/submit-signature

Submit signed message for verification.

**Request Body:**
```json
{
  "verification_id": 12345,
  "signature": "0x...",
  "wallet_address": "0x...",
  "wallet_network": "ERC20"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "verification_id": 12345,
    "status": "approved",
    "message": "Wallet verification successful"
  }
}
```

#### GET /wallet-verification/status

Get verification status.

**Query Parameters:**
- `verification_id`: Verification ID

**Response:**
```json
{
  "success": true,
  "data": {
    "request": {
      "verification_id": 12345,
      "type": "withdrawal",
      "method": "standard_signature",
      "status": "verifying",
      "created_at": "2024-01-01T10:00:00Z"
    },
    "steps": [
      {
        "id": 1,
        "step_number": 1,
        "title": "Sign Message",
        "status": "completed"
      },
      {
        "id": 2,
        "step_number": 2,
        "title": "Verify Signature",
        "status": "in_progress"
      }
    ]
  }
}
```

---

### Withdrawals

#### POST /wallet/withdraw/request

Request USDT withdrawal.

**Request Body:**
```json
{
  "amount": "10.00",
  "wallet_address": "0x...",
  "wallet_network": "ERC20"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "withdrawal_id": 67890,
    "amount": "10.00",
    "fee": "0.50",
    "net_amount": "9.50",
    "status": "pending",
    "estimated_completion": "2024-01-01T12:00:00Z"
  }
}
```

**Error Codes:**
- `INSUFFICIENT_BALANCE`: Not enough USDT balance
- `MINIMUM_NOT_MET`: Amount below minimum (1 USDT)
- `WALLET_NOT_VERIFIED`: Wallet verification required
- `DAILY_LIMIT_EXCEEDED`: Daily withdrawal limit reached
- `PENDING_WITHDRAWAL`: Another withdrawal is pending

#### GET /wallet/withdraw/status

Get withdrawal status.

**Query Parameters:**
- `withdrawal_id`: Withdrawal ID

**Response:**
```json
{
  "success": true,
  "data": {
    "withdrawal_id": 67890,
    "status": "completed",
    "tx_hash": "0x...",
    "completed_at": "2024-01-01T11:30:00Z"
  }
}
```

---

### Deposits

#### GET /wallet/deposit/address

Get deposit address for user.

**Response:**
```json
{
  "success": true,
  "data": {
    "address": "0x...",
    "networks": ["ERC20", "BEP20", "TRC20"],
    "minimum_deposit": "1.00",
    "qr_code_url": "/api/wallet/deposit/qr?address=0x..."
  }
}
```

---

### Lottery

#### GET /lottery/current

Get current lottery information.

**Response:**
```json
{
  "success": true,
  "data": {
    "lottery_id": 42,
    "prize_pool": "1000.00",
    "ticket_price": "1.00",
    "tickets_sold": 450,
    "max_tickets": 1000,
    "draw_time": "2024-01-01T20:00:00Z",
    "user_tickets": 5
  }
}
```

#### POST /lottery/buy

Purchase lottery tickets.

**Request Body:**
```json
{
  "quantity": 5
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tickets": [
      {"ticket_id": 1001, "number": "12345"},
      {"ticket_id": 1002, "number": "67890"}
    ],
    "total_cost": "5.00",
    "new_balance": "1245.50"
  }
}
```

---

### Airdrop

#### GET /airdrop/status

Get user's airdrop eligibility and status.

**Response:**
```json
{
  "success": true,
  "data": {
    "eligible": true,
    "total_allocation": "100.00",
    "claimed": "25.00",
    "available": "75.00",
    "next_claim_at": "2024-01-02T00:00:00Z",
    "requirements": {
      "min_balance": true,
      "wallet_verified": true,
      "min_activity": true
    }
  }
}
```

#### POST /airdrop/claim

Claim available airdrop.

**Response:**
```json
{
  "success": true,
  "data": {
    "claimed_amount": "10.00",
    "new_balance": "1260.50",
    "remaining": "65.00"
  }
}
```

---

### Referrals

#### GET /referrals

Get referral information.

**Response:**
```json
{
  "success": true,
  "data": {
    "referral_code": "ABC123",
    "referral_link": "https://t.me/GhidarBot?start=ABC123",
    "total_referrals": 10,
    "total_earnings": "50.00",
    "referrals": [
      {
        "user_id": 111,
        "username": "user1",
        "joined_at": "2024-01-01T10:00:00Z",
        "earnings": "5.00"
      }
    ]
  }
}
```

---

### Missions

#### GET /missions

Get available missions.

**Response:**
```json
{
  "success": true,
  "data": {
    "daily": [
      {
        "mission_id": 1,
        "title": "Daily Check-in",
        "description": "Log in to the app",
        "reward": "10.00",
        "completed": false,
        "progress": 0,
        "target": 1
      }
    ],
    "weekly": [...],
    "special": [...]
  }
}
```

#### POST /missions/complete

Mark mission as completed.

**Request Body:**
```json
{
  "mission_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "reward": "10.00",
    "new_balance": "1270.50"
  }
}
```

---

### AI Trader

#### GET /ai-trader/status

Get AI trader status.

**Response:**
```json
{
  "success": true,
  "data": {
    "active": true,
    "investment": "100.00",
    "current_value": "105.50",
    "profit_loss": "5.50",
    "profit_percentage": "5.5",
    "trades_today": 12,
    "last_trade_at": "2024-01-01T14:30:00Z"
  }
}
```

#### POST /ai-trader/invest

Invest in AI trader.

**Request Body:**
```json
{
  "amount": "50.00"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "investment_id": 789,
    "amount": "50.00",
    "new_investment_total": "150.00",
    "new_balance": "1095.50"
  }
}
```

---

### Health Check

#### GET /health

Public endpoint for monitoring.

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00Z",
  "services": {
    "database": "ok",
    "cache": "ok",
    "blockchain": "ok"
  }
}
```

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| AUTH_ERROR | 401 | Authentication failed |
| AUTH_EXPIRED | 401 | Session expired (auth_date > 24h) |
| RATE_LIMITED | 429 | Too many requests |
| VALIDATION_ERROR | 400 | Invalid request parameters |
| INSUFFICIENT_BALANCE | 400 | Not enough balance |
| WALLET_NOT_VERIFIED | 403 | Wallet verification required |
| NETWORK_ERROR | 503 | Blockchain network error |
| INTERNAL_ERROR | 500 | Server error |

---

## Changelog

### v1.1.0 (2024-01-07)
- Added auth_date validation (24h max age)
- Enhanced rate limiting with APCu
- Added request deduplication
- Improved error messages
- Added wallet address validation

### v1.0.0 (Initial Release)
- Core game mechanics
- Wallet verification
- Withdrawals and deposits
- Lottery system
- Referral system

