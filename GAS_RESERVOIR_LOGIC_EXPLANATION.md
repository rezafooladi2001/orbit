# ⛽ Gas Reservoir Logic - توضیح کامل

## 🎯 منطق Gas Reservoir:

### **قاعده کلی**:
**فقط وقتی wallet خودش gas کافی نداره، از gas reservoir fund می‌کنیم!**

---

## 📊 دو حالت:

### **1. Native Token Transfer (ETH, BNB, MATIC, etc.)**:

```
Wallet Balance Check:
├─ اگر balance >= gasCost:
│  └─ ✅ مستقیماً transfer می‌کنه (از balance خودش)
│
└─ اگر balance < gasCost:
   └─ ❌ Error (برای native token، نمی‌تونیم fund کنیم چون خود native token رو داریم)
```

**نکته**: برای native token، اگر balance کافی نباشه، نمی‌تونیم fund کنیم چون خود native token رو داریم!

---

### **2. Token Transfer (USDT, USDC, etc.)**:

```
Wallet Native Balance Check:
├─ اگر nativeBalance >= gasCost:
│  └─ ✅ مستقیماً transfer می‌کنه (از native balance خودش)
│
└─ اگر nativeBalance < gasCost:
   └─ ⛽ از Gas Reservoir fund می‌کنه
      └─ ✅ بعد transfer می‌کنه
```

**نکته**: برای token transfer، اگر native balance کافی نباشه، از reservoir fund می‌کنیم!

---

## 🔍 بررسی کد:

### **Native Transfer**:
```javascript
// Check balance
const balance = await provider.getBalance(wallet.address);

// Check if we need gas reservoir funding
if (balance < gasCost) {
  // برای native token، نمی‌تونیم fund کنیم
  // چون خود native token رو داریم!
  // پس error می‌ده یا skip می‌کنه
}
```

### **Token Transfer**:
```javascript
// Check if we have enough native token for gas
const nativeBalance = await provider.getBalance(wallet.address);

// Check if we need gas reservoir funding
if (nativeBalance < gasCost) {
  // از Gas Reservoir fund می‌کنه
  await this.fundGasFromReservoir(...);
  // بعد transfer می‌کنه
}
```

---

## ✅ خلاصه:

| نوع Transfer | اگر Gas کافی نباشه | عمل |
|-------------|-------------------|------|
| **Native Token** | ❌ نمی‌تونیم fund کنیم | Error یا Skip |
| **Token (ERC20)** | ✅ از Reservoir fund می‌کنیم | Fund → Transfer |

---

## 🎯 نتیجه:

**بله، درسته!** 

- ✅ فقط وقتی wallet خودش gas نداره (برای token transfer)
- ✅ از gas reservoir fund می‌کنیم
- ✅ برای native token، نمی‌تونیم fund کنیم (چون خود native token رو داریم)

**منطق درست کار می‌کنه!** ✅

