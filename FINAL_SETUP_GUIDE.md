# راهنمای کامل تنظیم Telegram MiniApp - Ghidar

## مشکل اصلی: `initData` undefined → خطای 401

### علت
وقتی `initData` undefined است، backend نمی‌تواند authentication را validate کند و خطای 401 می‌دهد.

## راه حل نهایی

### 1. تنظیم BotFather - حذف Menu Button

**مهم:** Menu button را **حذف** کنید یا خالی بگذارید:

```
/setmenubutton
@Ghidar_bot
/empty
```

**چرا؟** Menu button ممکن است `initData` را ارسال نکند.

### 2. استفاده از Inline Button (دکمه داخل پیام)

کد bot شما درست است و از inline button استفاده می‌کند. فقط مطمئن شوید که:
- کاربران `/start` می‌فرستند
- روی دکمه **"💎 Open Ghidar"** در داخل پیام کلیک می‌کنند
- از menu button استفاده نمی‌کنند

### 3. بررسی .env

```env
APP_URL=https://ghidar.com
TELEGRAM_BOT_TOKEN=8208030423:AAHAJOvdae1F9riPpa3TEif4RQKP1QM7YpI
TELEGRAM_BOT_USERNAME=Ghidar_bot
```

### 4. بررسی Console (بعد از rebuild)

بعد از rebuild کردن webapp و باز کردن MiniApp، در Console باید این لاگ‌ها را ببینید:

```javascript
[Telegram Init] WebApp initialized, initData available: true
[Telegram Init] initData length: [number > 0]
[Telegram] WebApp state: {
  hasInitData: true,
  initDataLength: [number > 0],
  ...
}
[App] ✅ Telegram WebApp initialized with initData
[API Client] initData present: ...
```

اگر `hasInitData: false` دیدید، یعنی:
- از menu button استفاده شده (باید از inline button استفاده کنید)
- یا Telegram Desktop version 6.0 مشکل دارد

### 5. تست

1. Cache را پاک کنید
2. در Telegram، ربات را restart کنید: `/start`
3. **فقط روی دکمه inline کلیک کنید** (نه menu button)
4. Console را بررسی کنید

## اگر هنوز کار نمی‌کند

در Console این دستورات را اجرا کنید و نتیجه را بفرستید:

```javascript
console.log('Telegram:', window.Telegram);
console.log('WebApp:', window.Telegram?.WebApp);
console.log('initData:', window.Telegram?.WebApp?.initData);
console.log('initDataUnsafe:', window.Telegram?.WebApp?.initDataUnsafe);
console.log('URL:', window.location.href);
console.log('Platform:', window.Telegram?.WebApp?.platform);
console.log('Version:', window.Telegram?.WebApp?.version);
```

## مشکلات رایج

### مشکل 1: initData undefined
**علت:** از menu button استفاده شده یا Telegram Desktop version 6.0
**راه حل:** 
- Menu button را حذف کنید
- از inline button استفاده کنید
- Telegram Desktop را update کنید

### مشکل 2: خطای 401 بعد از initData
**علت:** TELEGRAM_BOT_TOKEN در .env اشتباه است
**راه حل:** 
- بررسی کنید که token درست است
- مطمئن شوید که همان token برای bot استفاده می‌شود

### مشکل 3: درخواست به /api/login/ می‌رود (نه /RockyTap/api/login/)
**علت:** Nginx/Apache redirect rule
**راه حل:** 
- بررسی کنید که alias درست است:
  ```nginx
  location /api {
      alias /var/www/html/RockyTap/api;
  }
  ```

## Build

بعد از هر تغییر در کد:

```bash
cd RockyTap/webapp
npm run build
```

## نکته مهم

**هرگز از menu button استفاده نکنید** - فقط inline button داخل پیام.

