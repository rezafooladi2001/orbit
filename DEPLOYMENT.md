# راهنمای Deploy پروژه Orbit

این راهنما مراحل pull، build و push پروژه Orbit را روی سرور توضیح می‌دهد.

## مراحل Deploy روی سرور

### 1. اتصال به سرور
```bash
ssh user@your-server-ip
cd /path/to/orbit
```

### 2. Pull تغییرات جدید از Git
```bash
# بررسی وضعیت فعلی
git status

# Pull تغییرات جدید
git pull origin main

# یا اگر branch دیگری دارید:
git pull origin master

# در صورت وجود conflict، حل کنید و سپس:
git add .
git commit -m "Resolve conflicts"
```

### 3. نصب Dependencies (در صورت نیاز)

```bash
# برای Node.js projects
npm install

# یا برای PHP projects
composer install

# یا برای Python projects
pip install -r requirements.txt
```

### 4. Build پروژه

```bash
# برای React/TypeScript/Vite projects
npm run build

# یا برای Next.js
npm run build

# یا برای PHP (معمولاً نیاز به build نیست)
# فقط فایل‌ها را کپی کنید
```

### 5. کپی فایل‌های Build شده (در صورت نیاز)

```bash
# اگر فایل‌های build شده باید به جای دیگری کپی شوند:
cp -r dist/* /path/to/production/

# یا
rsync -av dist/ /path/to/production/
```

### 6. Restart سرویس‌ها

```bash
# اگر از PM2 استفاده می‌کنید:
pm2 restart orbit

# یا restart همه:
pm2 restart all

# اگر از systemd استفاده می‌کنید:
sudo systemctl restart orbit
sudo systemctl restart nginx

# اگر از Docker استفاده می‌کنید:
docker-compose restart
# یا
docker-compose up -d --build
```

### 7. بررسی لاگ‌ها

```bash
# PM2 logs
pm2 logs orbit

# systemd logs
sudo journalctl -u orbit -f

# Docker logs
docker-compose logs -f
```

## اسکریپت خودکار Deploy

یک اسکریپت bash برای automate کردن این مراحل:

```bash
#!/bin/bash
# deploy.sh

set -e  # Exit on error

echo "🚀 Starting Orbit deployment..."

# 1. Pull changes
echo "📥 Pulling latest changes..."
git pull origin main

# 2. Install dependencies
echo "📦 Installing dependencies..."
npm install

# 3. Build project
echo "🔨 Building project..."
npm run build

# 4. Copy build files (adjust path as needed)
echo "📦 Copying build files..."
# cp -r dist/* /path/to/production/

# 5. Restart services
echo "🔄 Restarting services..."
pm2 restart orbit || echo "PM2 not configured, skipping..."

echo "✅ Deployment complete!"
```

اجرای اسکریپت:
```bash
chmod +x deploy.sh
./deploy.sh
```

## Push تغییرات به Git

### از Local به Remote

```bash
# 1. بررسی تغییرات
git status

# 2. اضافه کردن فایل‌ها
git add .

# 3. Commit
git commit -m "Your commit message"

# 4. Push
git push origin main
```

### از Server به Remote (در صورت نیاز)

```bash
# اگر روی سرور تغییراتی دادید و می‌خواهید push کنید:
git add .
git commit -m "Server changes"
git push origin main
```

## نکات مهم

1. **Backup**: همیشه قبل از deploy، از دیتابیس و فایل‌های مهم backup بگیرید
2. **Testing**: بعد از deploy، حتماً تست کنید که همه چیز درست کار می‌کند
3. **Environment Variables**: مطمئن شوید که متغیرهای محیطی درست تنظیم شده‌اند
4. **Permissions**: بررسی کنید که فایل‌ها و پوشه‌ها permission درستی دارند
5. **Branch Strategy**: از branch مناسب استفاده کنید (main/master برای production)

## Troubleshooting

### مشکل: git pull خطا می‌دهد
```bash
# Stash تغییرات محلی
git stash

# Pull مجدد
git pull origin main

# بازگرداندن تغییرات
git stash pop
```

### مشکل: npm install خطا می‌دهد
```bash
# پاک کردن و نصب مجدد
rm -rf node_modules package-lock.json
npm install
```

### مشکل: Build خطا می‌دهد
```bash
# بررسی لاگ‌ها
npm run build 2>&1 | tee build.log

# بررسی version Node.js
node --version
```

### مشکل: PM2 restart نمی‌شود
```bash
# بررسی وضعیت
pm2 status

# بررسی لاگ‌ها
pm2 logs orbit

# حذف و اضافه مجدد
pm2 delete orbit
pm2 start ecosystem.config.js
```

