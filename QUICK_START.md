# راهنمای سریع Orbit Bot

## اتصال به Remote Repository

### 1. اگر Repository از قبل در GitHub/GitLab دارید:

```bash
cd /home/alirezz/orbit

# اضافه کردن remote
git remote add origin https://github.com/username/orbit.git

# یا با SSH:
git remote add origin git@github.com:username/orbit.git

# بررسی remote
git remote -v
```

### 2. Push اولین بار:

```bash
# Push به remote
git push -u origin main

# یا اگر branch شما master است:
git push -u origin master
```

## دستورات روزمره

### Pull تغییرات از سرور:
```bash
cd /home/alirezz/orbit
git pull origin main
```

### Push تغییرات به سرور:
```bash
cd /home/alirezz/orbit

# بررسی تغییرات
git status

# اضافه کردن فایل‌ها
git add .

# Commit
git commit -m "توضیح تغییرات"

# Push
git push origin main
```

### Build پروژه:
```bash
cd /home/alirezz/orbit

# نصب dependencies (اولین بار)
npm install

# Build
npm run build
```

## Workflow کامل روی سرور:

```bash
# 1. Pull
git pull origin main

# 2. Install (اگر package.json تغییر کرده)
npm install

# 3. Build
npm run build

# 4. Restart (اگر از PM2 استفاده می‌کنید)
pm2 restart orbit
```

## ساخت Repository جدید در GitHub

1. برو به https://github.com/new
2. نام repository را "orbit" بگذار
3. Public یا Private انتخاب کن
4. **توجه**: README, .gitignore, license را اضافه نکن (چون از قبل داریم)
5. Create repository
6. سپس دستورات زیر را اجرا کن:

```bash
cd /home/alirezz/orbit
git remote add origin https://github.com/username/orbit.git
git branch -M main
git push -u origin main
```

## نکات

- همیشه قبل از push، `git pull` بزن تا conflict نداشته باشی
- برای commit message‌های واضح استفاده کن
- از `.gitignore` استفاده کن تا فایل‌های اضافی commit نشوند

