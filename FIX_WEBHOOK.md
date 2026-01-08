# مشکل: Bot webhook 404 می‌دهد

## مشکل
Webhook URL به `https://ghidar.com/RockyTap/bot/index.php` تنظیم شده اما 404 برمی‌گرداند.

## راه حل

### 1. بررسی تنظیمات Web Server

اگر از **Nginx** استفاده می‌کنید، مطمئن شوید که این config وجود دارد:

```nginx
location /RockyTap/bot {
    alias /root/Ghidar_Private_Key_project/RockyTap/bot;
    index index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # یا socket PHP شما
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }
}
```

یا اگر از **Apache** استفاده می‌کنید:

```apache
Alias /RockyTap/bot /root/Ghidar_Private_Key_project/RockyTap/bot
<Directory /root/Ghidar_Private_Key_project/RockyTap/bot>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.1-fpm.sock|fcgi://localhost"
    </FilesMatch>
</Directory>
```

### 2. تست Webhook

بعد از تنظیم web server، این دستور را اجرا کنید:

```bash
curl https://ghidar.com/RockyTap/bot/index.php
```

اگر فایل PHP را نمایش داد یا خطای PHP نشان داد، یعنی کار می‌کند.

### 3. بررسی Webhook Status

```bash
curl "https://api.telegram.org/bot8208030423:AAHAJOvdae1F9riPpa3TEif4RQKP1QM7YpI/getWebhookInfo"
```

باید `"ok": true` و `"url": "https://ghidar.com/RockyTap/bot/index.php"` ببینید.

### 4. تست دستی

یک پیام `/start` به bot بفرستید و بررسی کنید که آیا پاسخ می‌دهد یا نه.

## نکات مهم

- مطمئن شوید که PHP-FPM یا mod_php درست کار می‌کند
- بررسی کنید که permissions فایل درست است: `chmod 644 RockyTap/bot/index.php`
- بررسی کنید که `.env` فایل درست تنظیم شده است

