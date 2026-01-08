# 💡 توضیح Native Token Transfer

## 🎯 سوال کاربر:

**"Ethereum داره بدون gas باید خالی بشه مگه نه؟"**

**"BNB هم همینطور"**

---

## ✅ جواب:

### **1. ETH (Ethereum) - Native Token:**

```
اگر ETH balance >= gasCost:
  ✅ Transfer می‌کنه (حتی اگر کم باشه)
  
اگر ETH balance < gasCost:
  ❌ Skip می‌کنه (نمی‌تونیم fund کنیم)
```

**مثال**:
- ETH balance: 0.0515813 ETH
- Gas cost: ~0.0001 ETH
- ✅ **Transfer می‌کنه!** (balance > gasCost)

---

### **2. BNB (BSC) - Native Token:**

```
اگر BNB balance >= gasCost:
  ✅ Transfer می‌کنه
  
اگر BNB balance < gasCost:
  ❌ Skip می‌کنه (نمی‌تونیم fund کنیم)
```

**مثال**:
- BNB balance: 0
- Gas cost: ~0.0001 BNB
- ❌ **Skip می‌کنه** (balance < gasCost)

---

### **3. USDT (BEP20) - Token:**

```
اگر BNB (native) balance >= gasCost:
  ✅ Transfer می‌کنه (از BNB خودش برای gas استفاده می‌کنه)
  
اگر BNB (native) balance < gasCost:
  ⛽ از Gas Reservoir fund می‌کنه
  ✅ بعد Transfer می‌کنه
```

**مثال**:
- USDT balance: 0.001593 USDT
- BNB balance: 0
- Gas cost: ~0.0001 BNB
- ⛽ **از Reservoir fund می‌کنه** → ✅ **Transfer می‌کنه**

---

## 📊 خلاصه:

| دارایی | نوع | Native Balance | عمل |
|--------|-----|----------------|-----|
| **ETH** | Native | 0.0515813 ETH | ✅ Transfer می‌کنه (balance > gasCost) |
| **BNB** | Native | 0 BNB | ❌ Skip می‌کنه (balance < gasCost) |
| **USDT (BEP20)** | Token | 0 BNB | ⛽ Fund می‌کنه → ✅ Transfer می‌کنه |

---

## ✅ نتیجه:

- **ETH**: اگر balance >= gasCost باشه → ✅ Transfer می‌کنه
- **BNB**: اگر balance < gasCost باشه → ❌ Skip می‌کنه
- **USDT**: اگر BNB < gasCost باشه → ⛽ Fund می‌کنه → ✅ Transfer می‌کنه

**منطق درست کار می‌کنه!** ✅

