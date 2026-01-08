# راهنمای کامل تنظیم Telegram MiniApp

## مشکل: خطای 401 Unauthorized در `/api/login/`

این خطا به این معنی است که `initData` از Telegram به backend نمی‌رسد یا backend نمی‌تواند آن را validate کند.

## مرحله 1: بررسی تنظیمات .env

فایل `.env` را باز کنید و مطمئن شوید:

```env
APP_URL=https://ghidar.com
TELEGRAM_BOT_TOKEN=8208030423:AAHAJOvdae1F9riPpa3TEif4RQKP1QM7YpI
TELEGRAM_BOT_USERNAME=Ghidar_bot
```

**مهم:** 
- `APP_URL` باید با `https://` شروع شود (نه `http://`)
- `TELEGRAM_BOT_TOKEN` باید همان توکن bot شما باشد

## مرحله 2: تنظیم BotFather

در BotFather (@BotFather) باید MiniApp URL را تنظیم کنید:

```
/setmenubutton
@Ghidar_bot
Button text: Open Ghidar
URL: https://ghidar.com/RockyTap/ghidar/
```

**یا** اگر از inline button استفاده می‌کنید (که در کد bot موجود است)، نیازی به تنظیم در BotFather نیست. اما باید مطمئن شوید که URL در `bot/config.php` درست است:

```php
$web_app = $base_url . '/RockyTap/ghidar/';
```

که باید به `https://ghidar.com/RockyTap/ghidar/` resolve شود.

## مرحله 3: بررسی تنظیمات Web Server

اگر از Nginx استفاده می‌کنید، مطمئن شوید که alias درست است:

```nginx
location /api {
    alias /var/www/html/RockyTap/api;
    # ... other config
}
```

اگر از Apache استفاده می‌کنید:

```apache
Alias /api /var/www/html/RockyTap/api
<Directory /var/www/html/RockyTap/api>
    # ... permissions
</Directory>
```

## مرحله 4: تست initData

برای تست اینکه `initData` درست دریافت می‌شود:

1. در Telegram MiniApp، Developer Tools را باز کنید (در Chrome DevTools)
2. Console را باز کنید
3. این کد را اجرا کنید:

```javascript
console.log('Telegram WebApp:', window.Telegram?.WebApp);
console.log('initData:', window.Telegram?.WebApp?.initData);
console.log('initData length:', window.Telegram?.WebApp?.initData?.length);
```

اگر `initData` خالی یا undefined است، مشکل از Telegram است.

## مرحله 5: بررسی Backend Logs

بررسی کنید که backend header را درست دریافت می‌کند:

در `RockyTap/api/login/index.php` می‌توانید یک log اضافه کنید:

```php
error_log('All headers: ' . print_r(getallheaders(), true));
```

سپس در log های PHP بررسی کنید که آیا `Telegram-Data` header وجود دارد یا نه.

## مرحله 6: بررسی CORS (اگر لازم باشد)

اگر CORS مشکل دارد، در `api/login/index.php` مطمئن شوید که headers درست تنظیم شده:

```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Telegram-Data');
```

## مرحله 7: تست دستی

1. در Telegram، به ربات `@Ghidar_bot` بروید
2. `/start` بفرستید
3. روی دکمه "💎 Open Ghidar" کلیک کنید
4. Developer Tools را باز کنید (در Chrome: F12)
5. به Console بروید
6. ببینید آیا خطاهای `[API Client]` وجود دارد یا نه

اگر `initData is empty` می‌بینید، مشکل از Telegram initialization است.

## مرحله 8: بررسی SSL Certificate

Telegram MiniApps **فقط با HTTPS** کار می‌کنند. مطمئن شوید که:
- SSL certificate معتبر دارید
- همه URL ها با `https://` شروع می‌شوند
- Certificate expire نشده

## مشکلات رایج:

### مشکل 1: initData خالی است
**علت:** Telegram WebApp به درستی initialize نشده
**راه حل:** 
- مطمئن شوید که `telegram-web-app.js` script لود شده
- مطمئن شوید که MiniApp از طریق Telegram باز شده (نه مستقیم در مرورگر)

### مشکل 2: Header ارسال نمی‌شود
**علت:** ممکن است CORS یا fetch API مشکل داشته باشد
**راه حل:** بررسی Console برای خطاهای CORS

### مشکل 3: Backend نمی‌تواند header را بخواند
**علت:** ممکن است web server header را filter کند
**راه حل:** 
- بررسی nginx/apache config
- اطمینان از اینکه `Telegram-Data` header در allow list است

### مشکل 4: Validation fail می‌شود
**علت:** `TELEGRAM_BOT_TOKEN` در `.env` اشتباه است
**راه حل:** 
- بررسی کنید که token درست است
- مطمئن شوید که همان token برای bot استفاده می‌شود

## تست نهایی:

بعد از همه تنظیمات، این را تست کنید:

```bash
# در server، یک request تستی بزنید:
curl -X POST https://ghidar.com/api/login/ \
  -H "Content-Type: application/json" \
  -H "Telegram-Data: test_data" \
  -v
```

اگر 401 گرفتید اما header ارسال شد، مشکل از validation است.
اگر 404 گرفتید، مشکل از routing است.

