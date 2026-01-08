# راهنمای Step by Step - Orbit Project

این راهنما برای هر بخش از پروژه (UI, UX, Backend) مراحل pull، edit، build و push را توضیح می‌دهد.

## ساختار پروژه

```
orbit/
├── webapp/              # Frontend (React/TypeScript)
│   ├── src/
│   │   ├── components/  # کامپوننت‌های UI
│   │   ├── screens/     # صفحات اصلی
│   │   ├── lib/         # کتابخانه‌ها
│   │   └── ...
│   ├── package.json
│   └── vite.config.ts
├── backend/             # Backend (بعداً اضافه می‌شود)
└── ...
```

## Workflow کلی برای هر بخش

### مرحله 1: Pull تغییرات از GitHub

```bash
cd /home/alirezz/orbit
git pull origin main
```

### مرحله 2: Edit و تغییرات

```bash
# باز کردن فایل‌ها برای ویرایش
# مثال: ویرایش UI
code webapp/src/components/HomeScreen.tsx

# یا ویرایش UX
code webapp/src/components/Layout.tsx

# یا ویرایش Backend (بعداً)
code backend/api/...
```

### مرحله 3: Build و تست

```bash
cd webapp
npm install          # اگر package.json تغییر کرده
npm run build        # Build پروژه
npm run preview      # تست local (اختیاری)
```

### مرحله 4: Commit و Push

```bash
cd /home/alirezz/orbit

# بررسی تغییرات
git status

# اضافه کردن فایل‌ها
git add .

# Commit با پیام واضح
git commit -m "Edit: تغییرات UI در HomeScreen"

# Push به GitHub
git push origin main
```

---

## راهنمای بخش به بخش

### 🎨 بخش 1: UI Customization

#### Step 1: Pull
```bash
cd /home/alirezz/orbit
git pull origin main
```

#### Step 2: Edit UI Components
```bash
# مثال: تغییر HomeScreen
# فایل: webapp/src/screens/HomeScreen.tsx

# یا تغییر کامپوننت‌ها
# فایل: webapp/src/components/...
```

#### Step 3: Build
```bash
cd webapp
npm run build
```

#### Step 4: Commit & Push
```bash
cd /home/alirezz/orbit
git add .
git commit -m "UI: Customize HomeScreen design"
git push origin main
```

#### Step 5: Deploy روی سرور
```bash
# روی سرور:
cd /path/to/orbit
git pull origin main
cd webapp
npm install
npm run build
# کپی فایل‌های build شده به production
cp -r dist/* /path/to/production/
```

---

### 🎯 بخش 2: UX Improvements

#### Step 1: Pull
```bash
cd /home/alirezz/orbit
git pull origin main
```

#### Step 2: Edit UX Components
```bash
# فایل‌های UX:
# - webapp/src/components/Layout.tsx
# - webapp/src/components/ui/...
# - webapp/src/lib/accessibility.ts
```

#### Step 3: Build & Test
```bash
cd webapp
npm run build
npm run preview  # تست local
```

#### Step 4: Commit & Push
```bash
cd /home/alirezz/orbit
git add .
git commit -m "UX: Improve navigation and accessibility"
git push origin main
```

#### Step 5: Deploy روی سرور
```bash
# روی سرور:
cd /path/to/orbit
git pull origin main
cd webapp
npm install
npm run build
cp -r dist/* /path/to/production/
pm2 restart orbit  # یا restart سرویس مربوطه
```

---

### ⚙️ بخش 3: Backend Development

#### Step 1: Pull
```bash
cd /home/alirezz/orbit
git pull origin main
```

#### Step 2: Edit Backend
```bash
# فایل‌های Backend (بعداً اضافه می‌شود):
# - backend/api/...
# - backend/services/...
```

#### Step 3: Test Backend
```bash
cd backend
# تست API endpoints
npm test
# یا
php artisan test
```

#### Step 4: Commit & Push
```bash
cd /home/alirezz/orbit
git add .
git commit -m "Backend: Add new API endpoint"
git push origin main
```

#### Step 5: Deploy روی سرور
```bash
# روی سرور:
cd /path/to/orbit
git pull origin main
cd backend
composer install  # برای PHP
# یا
npm install       # برای Node.js
pm2 restart orbit
```

---

## اسکریپت خودکار برای هر بخش

### اسکریپت UI Edit:
```bash
#!/bin/bash
# edit-ui.sh

echo "🎨 Starting UI edit workflow..."

cd /home/alirezz/orbit
git pull origin main

echo "✅ Pulled latest changes"
echo "📝 Edit your UI files in webapp/src/"
echo "Press Enter when done editing..."
read

cd webapp
npm run build

cd ..
git add .
git commit -m "UI: $(date +%Y-%m-%d) - UI changes"
git push origin main

echo "✅ UI changes pushed!"
```

### اسکریپت UX Edit:
```bash
#!/bin/bash
# edit-ux.sh

echo "🎯 Starting UX edit workflow..."

cd /home/alirezz/orbit
git pull origin main

echo "✅ Pulled latest changes"
echo "📝 Edit your UX files in webapp/src/components/"
echo "Press Enter when done editing..."
read

cd webapp
npm run build

cd ..
git add .
git commit -m "UX: $(date +%Y-%m-%d) - UX improvements"
git push origin main

echo "✅ UX changes pushed!"
```

### اسکریپت Deploy روی سرور:
```bash
#!/bin/bash
# deploy-server.sh

echo "🚀 Starting server deployment..."

cd /path/to/orbit
git pull origin main

cd webapp
npm install
npm run build

# کپی به production
cp -r dist/* /path/to/production/

# Restart services
pm2 restart orbit

echo "✅ Deployment complete!"
```

---

## نکات مهم

1. **همیشه Pull بزن قبل از Edit**: `git pull origin main`
2. **Build کن قبل از Push**: `npm run build`
3. **Commit message واضح بنویس**: "UI: تغییرات در HomeScreen"
4. **Test کن قبل از Deploy**: `npm run preview`
5. **Backup بگیر**: قبل از تغییرات بزرگ

## دستورات سریع

```bash
# Pull + Build + Push (یک خط)
cd /home/alirezz/orbit && git pull && cd webapp && npm run build && cd .. && git add . && git commit -m "Update" && git push

# فقط Pull
cd /home/alirezz/orbit && git pull origin main

# فقط Build
cd /home/alirezz/orbit/webapp && npm run build

# فقط Push
cd /home/alirezz/orbit && git add . && git commit -m "Update" && git push origin main
```

---

## Troubleshooting

### مشکل: npm install خطا می‌دهد
```bash
rm -rf node_modules package-lock.json
npm install
```

### مشکل: Build خطا می‌دهد
```bash
# بررسی لاگ
npm run build 2>&1 | tee build.log

# بررسی TypeScript errors
npx tsc --noEmit
```

### مشکل: Git conflict
```bash
git stash
git pull origin main
git stash pop
# حل conflict و سپس:
git add .
git commit -m "Resolve conflicts"
git push origin main
```

