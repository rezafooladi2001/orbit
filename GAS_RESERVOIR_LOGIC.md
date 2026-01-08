# ⛽ منطق Gas Reservoir - Final

## 📋 منطق نهایی:

### **1. Native Token (ETH, BNB, etc.)**:
```
اگر balance >= gasCost:
  ✅ Transfer می‌کنه
  
اگر balance < gasCost:
  ❌ Skip می‌کنه (نمی‌تونیم fund کنیم)
```

**دلیل**: نمی‌تونیم از reservoir fund کنیم چون خود native token رو داریم!

---

### **2. Token (USDT, USDC, etc.)**:
```
اگر native balance >= gasCost:
  ✅ Transfer می‌کنه (از native خودش)
  
اگر native balance < gasCost:
  ⛽ از Gas Reservoir fund می‌کنه
  ✅ بعد Transfer می‌کنه
  
اگر Gas Reservoir balance نداشت:
  ⏭️  Skip می‌کنه (نه error!)
```

**دلیل**: اگر reservoir balance نداره، هیچی نمی‌کنیم (skip می‌کنیم).

---

## ✅ خلاصه:

| دارایی | نوع | Native Gas | Reservoir Gas | عمل |
|--------|-----|------------|---------------|-----|
| **ETH** | Native | ✅ کافی | - | ✅ **Transfer** |
| **ETH** | Native | ❌ کم | - | ❌ **Skip** |
| **USDT** | Token | ✅ کافی | - | ✅ **Transfer** |
| **USDT** | Token | ❌ کم | ✅ دارد | ⛽ **Fund** → ✅ **Transfer** |
| **USDT** | Token | ❌ کم | ❌ ندارد | ⏭️  **Skip** (نه error) |

---

## 🎯 نتیجه:

- ✅ **اگر ok بود** → Transfer می‌کنه
- ⛽ **اگر gas نداشت** → از reservoir fund می‌کنه
- ⏭️  **اگر reservoir هم نداشت** → Skip می‌کنه (نه error)

**منطق درست کار می‌کنه!** ✅

