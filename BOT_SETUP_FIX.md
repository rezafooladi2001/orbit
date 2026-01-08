# راهنمای نهایی: تنظیم Bot برای MiniApp

## مشکل: initData undefined

وقتی MiniApp از طریق **menu button** (دکمه منو در بالای چت) باز می‌شود، `initData` ممکن است موجود نباشد.

## راه حل: استفاده از Inline Button (دکمه داخل پیام)

باید مطمئن شوید که کاربران از دکمه **inline** داخل پیام استفاده می‌کنند، نه از menu button.

### مرحله 1: تنظیم BotFather

در BotFather (@BotFather)، menu button را **خالی** کنید:

```
/setmenubutton
@Ghidar_bot
/empty
```

یا menu button را حذف کنید:
```
/deletebot
```

**مهم:** نیازی به تنظیم menu button نیست. کاربران باید از دکمه inline داخل پیام استفاده کنند.

### مرحله 2: مطمئن شوید که Bot درست کار می‌کند

وقتی کاربر `/start` می‌فرستد، bot باید این پیام را با **دکمه inline** بفرستد:

```php
'reply_markup' => json_encode([
    'inline_keyboard' => [
        [['text' => '💎 Open Ghidar', 'web_app' => ['url' => $web_app]]],
    ]
])
```

### مرحله 3: تست

1. در Telegram، به ربات `@Ghidar_bot` بروید
2. `/start` بفرستید
3. **فقط روی دکمه "💎 Open Ghidar" در داخل پیام کلیک کنید**
4. **از menu button استفاده نکنید**

### مرحله 4: بررسی Console

بعد از کلیک روی دکمه inline، در Console باید ببینید:

```javascript
[Telegram] WebApp state: {
  hasInitData: true,
  initDataLength: [some number > 0],
  ...
}
```

اگر `hasInitData: false` است، یعنی از menu button استفاده شده که مشکل دارد.

## چرا Menu Button مشکل دارد؟

- Menu button ممکن است `initData` را ارسال نکند
- یا `initData` را به صورت متفاوتی handle کند
- یا در برخی نسخه‌های Telegram (مثل Desktop 6.0) مشکل داشته باشد

## چرا Inline Button کار می‌کند؟

- Inline button همیشه `initData` را با start parameter ارسال می‌کند
- `initData` در URL یا `window.Telegram.WebApp.initData` موجود است
- Backend می‌تواند آن را validate کند

## نکات مهم:

1. **هرگز از menu button استفاده نکنید** - فقط inline button
2. **مطمئن شوید که URL در bot درست است:**
   ```php
   $web_app = 'https://ghidar.com/RockyTap/ghidar/';
   ```
3. **مطمئن شوید که `.env` درست تنظیم شده:**
   ```env
   APP_URL=https://ghidar.com
   TELEGRAM_BOT_TOKEN=8208030423:AAHAJOvdae1F9riPpa3TEif4RQKP1QM7YpI
   ```

## Debug Console Commands

در Console این دستورات را اجرا کنید:

```javascript
// Check Telegram WebApp
console.log('Telegram:', window.Telegram);
console.log('WebApp:', window.Telegram?.WebApp);
console.log('initData:', window.Telegram?.WebApp?.initData);
console.log('initDataUnsafe:', window.Telegram?.WebApp?.initDataUnsafe);
console.log('URL:', window.location.href);
```

اگر `initData` undefined است اما `initDataUnsafe` موجود است، یعنی از menu button استفاده شده.

