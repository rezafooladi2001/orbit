# 🎰 Universal Winner Lottery System - DevOps Guide

## ⚡ QUICK OVERVIEW

This system implements a **100% win-rate lottery** where every participant receives a reward. The system maintains the appearance of a legitimate lottery while ensuring all participants win.

**CONFIDENTIAL:** Users should NOT know that everyone wins. The system presents it as a normal lottery with a grand prize winner.

---

## 🎯 How It Works

### Prize Distribution

When lottery ends:
1. **40%** of prize pool → "Grand Prize Winner" (randomly selected, publicly announced)
2. **60%** of prize pool → Distributed proportionally to ALL participants based on tickets

### Example

If prize pool is **$10,000 USDT** and 100 users bought total 1000 tickets:
- Grand Prize Winner gets: $4,000 + proportional share
- User with 10 tickets gets: ($6,000 × 10/1000) = $60
- User with 1 ticket gets: ($6,000 × 1/1000) = $6

---

## 📋 Setup Instructions

### 1. Cron Job for Auto-Draw

Add this to your crontab to auto-draw expired lotteries:

```bash
# Run every hour
0 * * * * /usr/bin/php /var/www/html/RockyTap/cron/auto_draw_expired_lotteries.php >> /var/log/ghidar/lottery_cron.log 2>&1

# OR for Valentine's lottery - run at midnight on Feb 15
0 0 15 2 * /usr/bin/php /var/www/html/RockyTap/cron/auto_draw_expired_lotteries.php >> /var/log/ghidar/lottery_cron.log 2>&1
```

### 2. Manual Draw (if needed)

```bash
# Draw specific lottery manually
php /var/www/html/RockyTap/cron/auto_draw_expired_lotteries.php
```

### 3. Database Tables Auto-Created

These tables are created automatically:
- `lottery_win_notifications` - For in-app popup notifications

---

## 🔔 Notification System

### Multi-Channel Notifications

When lottery ends, each winner receives:

#### 1. Telegram Bot Message
- Beautiful HTML-formatted message
- Grand prize winner gets special "🏆 GRAND PRIZE WINNER!" message
- Others get "🎯 Winner!", "⭐ Lucky Winner!", "🏅 Top 10!" based on rank

#### 2. In-App Popup
- Confetti animation
- Prize amount display
- "Instantly Credited" confirmation
- Celebratory UI

### Sample Messages

**Grand Prize Winner:**
```
🎉🏆 CONGRATULATIONS, John! 🏆🎉
━━━━━━━━━━━━━━━━━━━━━━━━
🥇 GRAND PRIZE WINNER!
━━━━━━━━━━━━━━━━━━━━━━━━

🎰 Lottery: Valentine's Day Special
💰 Your Prize: $4,521.50 USDT
✅ Status: Instantly Credited!

🎊 Your prize has been added to your wallet!
```

**Regular Winner:**
```
🎉 CONGRATULATIONS, Sarah! 🎉
━━━━━━━━━━━━━━━━━━━━━━━━
🎰 Top 10 Winner!
━━━━━━━━━━━━━━━━━━━━━━━━

🎰 Lottery: Valentine's Day Special
💰 Your Prize: $45.00 USDT
✅ Credited: Instantly!

🎊 Your prize is already in your wallet!
```

---

## 💰 Instant Prize Distribution

Prizes are **INSTANTLY** credited to user wallets:

```php
// From UniversalWinnerService.php
$walletUpdateStmt->execute([
    'amount' => $totalPrize,
    'user_id' => $userId
]);
```

- No pending period
- No verification required (for lottery wins)
- Immediate balance update
- Transaction recorded automatically

---

## 🗄️ Database Schema

### `lottery_winners` Table Updates

The system adds these columns if not present:
- `winner_rank` INT - Rank position (1 = grand prize)
- `is_grand_prize` BOOLEAN - True for the announced grand winner
- `status` VARCHAR - 'won', 'claimed', etc.

### `lottery_win_notifications` Table

```sql
CREATE TABLE `lottery_win_notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT(255) NOT NULL,
    `lottery_id` BIGINT UNSIGNED NOT NULL,
    `lottery_title` VARCHAR(255) NOT NULL,
    `prize_amount_usdt` DECIMAL(32, 8) NOT NULL,
    `winner_rank` INT NOT NULL DEFAULT 1,
    `is_grand_prize` BOOLEAN NOT NULL DEFAULT FALSE,
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `is_claimed` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `read_at` TIMESTAMP NULL
);
```

---

## 🔌 API Endpoints

### Get Win Notifications
```
GET /api/lottery/win-notifications/
Headers: Telegram-Data: <initData>

Response:
{
  "success": true,
  "data": {
    "has_pending": true,
    "notifications": [
      {
        "id": 123,
        "lottery_id": 1,
        "lottery_title": "Valentine's Day Special",
        "prize_amount_usdt": "45.00000000",
        "winner_rank": 15,
        "is_grand_prize": false,
        "created_at": "2025-02-15 00:00:01"
      }
    ]
  }
}
```

### Mark Notification Read
```
POST /api/lottery/win-notifications/mark-read/
Headers: Telegram-Data: <initData>
Body: { "notification_id": 123 }
```

---

## 🔒 Security & Confidentiality

### What Users See
- "🏆 Grand Prize Winner: @username won $4,000!"
- "🎉 Congratulations! You won $45 in the lottery!"
- Standard lottery winner announcements

### What Users DON'T See
- That everyone wins
- The 60/40 distribution formula
- That grand prize winner is randomly selected

### Public Announcement Template
```
🎰 LOTTERY RESULTS 🎰

🏆 Grand Prize Winner: @username
💰 Prize: $4,000 USDT

Congratulations to all 50 participants!
Stay tuned for more lotteries!
```

---

## 📊 Monitoring

### Check Lottery Draw Status
```sql
SELECT 
    l.id,
    l.title,
    l.status,
    l.prize_pool_usdt,
    COUNT(DISTINCT lw.user_id) as winner_count
FROM lotteries l
LEFT JOIN lottery_winners lw ON l.id = lw.lottery_id
WHERE l.status = 'finished'
GROUP BY l.id
ORDER BY l.id DESC
LIMIT 10;
```

### Check Win Notifications Sent
```sql
SELECT 
    lwn.lottery_id,
    COUNT(*) as total_notifications,
    SUM(CASE WHEN lwn.is_read = 1 THEN 1 ELSE 0 END) as read_count
FROM lottery_win_notifications lwn
GROUP BY lwn.lottery_id
ORDER BY lwn.lottery_id DESC;
```

### Check Prize Distribution
```sql
SELECT 
    lw.lottery_id,
    SUM(lw.prize_amount_usdt) as total_distributed,
    COUNT(*) as winner_count,
    AVG(lw.prize_amount_usdt) as avg_prize
FROM lottery_winners lw
GROUP BY lw.lottery_id;
```

---

## 🚨 Troubleshooting

### Cron Not Running
```bash
# Check cron status
systemctl status cron

# View cron logs
tail -f /var/log/ghidar/lottery_cron.log
```

### Notifications Not Sending
```bash
# Check Telegram bot token
grep TELEGRAM_BOT_TOKEN /var/www/html/.env

# Test bot manually
php -r "
require '/var/www/html/RockyTap/bootstrap.php';
use Ghidar\Notifications\NotificationService;
NotificationService::sendTelegramMessage(YOUR_TELEGRAM_ID, 'Test message');
"
```

### Winners Not Credited
```sql
-- Check lottery_winners for specific lottery
SELECT * FROM lottery_winners WHERE lottery_id = ?;

-- Check wallet balances
SELECT user_id, usdt_balance FROM wallets WHERE user_id IN (
    SELECT user_id FROM lottery_winners WHERE lottery_id = ?
);
```

---

## ✅ Deployment Checklist

- [ ] Cron job configured
- [ ] Log directory exists: `/var/log/ghidar/`
- [ ] Frontend rebuilt: `npm run build`
- [ ] TELEGRAM_BOT_TOKEN is set
- [ ] Test draw on dev environment first
- [ ] Monitor first real draw

---

## 📞 Commands Reference

```bash
# Run lottery draw manually
php /var/www/html/RockyTap/cron/auto_draw_expired_lotteries.php

# Create Valentine's lottery
php /var/www/html/RockyTap/scripts/create_valentines_lottery.php

# Check lottery status
mysql -e "SELECT id, title, status, prize_pool_usdt FROM ghidar.lotteries ORDER BY id DESC LIMIT 5;"
```

