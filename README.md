# Orbit Bot Project

پروژه Orbit Bot - انتقال UI از Ghidar و Customization

## ساختار پروژه

```
orbit/
├── webapp/              # Frontend (React/TypeScript/Vite)
│   ├── src/
│   │   ├── components/  # کامپوننت‌های UI
│   │   ├── screens/     # صفحات اصلی
│   │   ├── lib/         # کتابخانه‌ها و utilities
│   │   └── ...
│   ├── package.json
│   └── vite.config.ts
├── backend/             # Backend (بعداً اضافه می‌شود)
└── docs/                # مستندات
```

## نصب و راه‌اندازی

### 1. Clone پروژه
```bash
git clone https://github.com/rezafooladi2001/orbit.git
cd orbit
```

### 2. نصب Dependencies
```bash
cd webapp
npm install
```

### 3. Development
```bash
npm run dev
```

### 4. Build
```bash
npm run build
```

## Workflow

برای هر بخش (UI, UX, Backend) از راهنمای `STEP_BY_STEP_GUIDE.md` استفاده کنید.

### مراحل کلی:
1. **Pull**: `git pull origin main`
2. **Edit**: ویرایش فایل‌ها
3. **Build**: `npm run build`
4. **Commit**: `git commit -m "توضیح تغییرات"`
5. **Push**: `git push origin main`
6. **Deploy**: روی سرور pull و build کن

## مستندات

- `STEP_BY_STEP_GUIDE.md` - راهنمای کامل step by step
- `DEPLOYMENT.md` - راهنمای deploy روی سرور
- `QUICK_START.md` - راهنمای سریع

## نکات مهم

- همیشه قبل از edit، `git pull` بزن
- قبل از push، `npm run build` کن
- Commit message واضح بنویس
- روی سرور بعد از pull، build کن

## توسعه

این پروژه از UI پروژه Ghidar شروع شده و به تدریج customize می‌شود.

### مراحل بعدی:
- ✅ انتقال UI از Ghidar
- 🔄 Customization UI
- ⏳ بهبود UX
- ⏳ توسعه Backend
