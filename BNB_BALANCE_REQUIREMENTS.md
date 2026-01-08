# 💰 BNB Balance Requirements

## 📋 نیازمندی‌های BNB Balance:

### **1. User Wallet (کیف پول کاربر)**:

#### **برای Native BNB Transfer**:
```
حداقل: ~0.0001 BNB (برای gas)
توصیه شده: ~0.0002 BNB (با buffer)
```

**منطق**:
- اگر `BNB balance >= gasCost` → ✅ Transfer می‌کنه
- اگر `BNB balance < gasCost` → ❌ Skip می‌کنه

**مثال**:
- Gas cost: ~0.0001 BNB
- اگر balance = 0.00015 BNB → ✅ Transfer می‌کنه (0.00005 BNB transfer می‌شه)
- اگر balance = 0.00005 BNB → ❌ Skip می‌کنه (کمتر از gas cost)

---

#### **برای Token Transfer (USDT, etc.)**:
```
حداقل: ~0.0001 BNB (برای gas)
اگر نداشت: از Reservoir fund می‌کنه
```

**منطق**:
- اگر `BNB balance >= gasCost` → ✅ Transfer می‌کنه (از BNB خودش)
- اگر `BNB balance < gasCost` → ⛽ از Reservoir fund می‌کنه → ✅ Transfer می‌کنه

---

### **2. Reservoir Wallet (کیف پول مقصد/Reservoir)**:

#### **برای Fund کردن Token Transfers**:
```
حداقل: ~0.0002 BNB per transfer
توصیه شده: 0.05 - 0.1 BNB (برای چندین transfer)
```

**منطق**:
- برای هر token transfer که gas نداره:
  - `gasCost` برای token transfer (~0.0001 BNB)
  - `reservoirTxGas` برای funding transaction (~0.0001 BNB)
  - **Total**: ~0.0002 BNB per transfer

**مثال**:
- اگر 10 token transfer نیاز به fund داشته باشن:
  - نیاز: 10 × 0.0002 = 0.002 BNB
- اگر 100 token transfer نیاز به fund داشته باشن:
  - نیاز: 100 × 0.0002 = 0.02 BNB

---

## 📊 خلاصه:

| Wallet | نوع | حداقل | توصیه شده | استفاده |
|--------|-----|-------|------------|---------|
| **User** | Native BNB | 0.0001 BNB | 0.0002 BNB | برای transfer خود BNB |
| **User** | Token Gas | 0.0001 BNB | - | برای transfer token (یا از reservoir) |
| **Reservoir** | Fund Token | 0.0002 BNB | 0.05-0.1 BNB | برای fund کردن token transfers |

---

## ✅ نتیجه:

### **User Wallet**:
- **برای BNB transfer**: حداقل 0.0001 BNB
- **برای Token transfer**: اگر نداشت، از reservoir fund می‌کنه

### **Reservoir Wallet**:
- **حداقل**: 0.05 BNB (برای چندین transfer)
- **توصیه شده**: 0.1 BNB (برای اطمینان)

---

**منطق درست کار می‌کنه!** ✅

