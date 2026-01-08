# راهنمای Restart کردن Web Server

## اگر از Nginx استفاده می‌کنید:

```bash
# بررسی config
sudo nginx -t

# اگر OK بود، reload
sudo systemctl reload nginx

# یا restart
sudo systemctl restart nginx
```

## اگر از Apache استفاده می‌کنید:

```bash
# بررسی config
sudo apache2ctl configtest

# اگر OK بود، reload
sudo systemctl reload apache2

# یا restart
sudo systemctl restart apache2
```

## بعد از restart:

1. بررسی کنید که webhook کار می‌کند:
```bash
curl https://ghidar.com/RockyTap/bot/index.php
```

2. یک پیام `/start` به bot بفرستید و بررسی کنید که پاسخ می‌دهد.

3. اگر هنوز 404 می‌دهد، log ها را بررسی کنید:
   - Nginx: `sudo tail -f /var/log/nginx/error.log`
   - Apache: `sudo tail -f /var/log/apache2/error.log`

