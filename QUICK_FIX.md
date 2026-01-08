# راهنمای سریع برای اجرای Ghidar MiniApp

## مشکل: MiniApp در Telegram بالا نمی‌آید

### مرحله 1: بررسی تنظیمات .env
فایل `.env` را باز کنید و مطمئن شوید:

```env
APP_URL=https://ghidar.com  # یا آدرس واقعی سایت شما
TELEGRAM_BOT_TOKEN=8208030423:AAHAJOvdae1F9riPpa3TEif4RQKP1QM7YpI
TELEGRAM_BOT_USERNAME=Ghidar_bot
```

**مهم:** `APP_URL` باید با `https://` شروع شود (نه `http://`)

### مرحله 2: Build کردن WebApp
```bash
cd RockyTap/webapp
npm install  # اگر قبلا نصب نکرده‌اید
npm run build
```

بعد از build، فایل‌ها باید در `RockyTap/assets/ghidar/` قرار بگیرند:
- `index.js`
- `index.css`
- سایر فایل‌های build شده

### مرحله 3: بررسی BotFather
در BotFather (@BotFather) بررسی کنید که Mini App URL تنظیم شده:

```
/setmenubutton
@Ghidar_bot
Button text: Open Ghidar
URL: https://ghidar.com/RockyTap/ghidar/
```

یا اگر از دکمه inline استفاده می‌کنید (که در کد bot موجود است)، نیازی به تنظیم در BotFather نیست.

### مرحله 4: تست
1. به ربات `@Ghidar_bot` پیام `/start` بفرستید
2. روی دکمه "💎 Open Ghidar" کلیک کنید
3. MiniApp باید باز شود

### اگر هنوز کار نمی‌کند:

1. **بررسی Console در مرورگر:**
   - در Telegram، بعد از باز شدن MiniApp، Developer Tools را باز کنید
   - به دنبال خطاهای JavaScript بگردید

2. **بررسی مسیر فایل‌ها:**
   ```bash
   ls -la RockyTap/assets/ghidar/
   ```
   باید فایل‌های `index.js` و `index.css` را ببینید

3. **بررسی دسترسی URL:**
   مرورگر را باز کنید و به این آدرس بروید:
   ```
   https://ghidar.com/RockyTap/ghidar/
   ```
   باید صفحه خالی یا loader را ببینید (نه 404)

4. **بررسی SSL:**
   Telegram MiniApps فقط با HTTPS کار می‌کنند

### مشکل احتمالی: فایل‌های build قدیمی
اگر فایل‌های build قدیمی هستند، cache را پاک کنید و دوباره build کنید:

```bash
cd RockyTap/webapp
rm -rf node_modules/.vite
rm -rf ../assets/ghidar/*
npm run build
```

