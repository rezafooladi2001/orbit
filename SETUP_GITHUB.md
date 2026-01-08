# راهنمای ساخت Repository در GitHub

## مراحل ساخت Repository در GitHub:

### 1. ساخت Repository جدید:

1. برو به: https://github.com/new
2. Repository name: `orbit`
3. Description (اختیاری): "Orbit Bot Project"
4. Public یا Private انتخاب کن
5. **مهم**: تیک‌های زیر را **بردار** (چون از قبل داریم):
   - ❌ Add a README file
   - ❌ Add .gitignore
   - ❌ Choose a license
6. روی **Create repository** کلیک کن

### 2. بعد از ساخت Repository، دستورات زیر را اجرا کن:

```bash
cd /home/alirezz/orbit

# بررسی remote (باید origin را ببینی)
git remote -v

# اگر origin درست است، push کن:
git push -u origin main
```

### 3. اگر repository از قبل وجود دارد و می‌خواهی محتوا را جایگزین کنی:

```bash
cd /home/alirezz/orbit

# Pull محتوای موجود (اگر چیزی هست)
git pull origin main --allow-unrelated-histories

# یا اگر می‌خواهی force push کنی (مواظب باش!):
# git push -u origin main --force
```

## دستورات آماده:

```bash
# 1. بررسی وضعیت
cd /home/alirezz/orbit
git status

# 2. اضافه کردن remote (اگر نیاز بود)
git remote remove origin  # اگر از قبل وجود دارد
git remote add origin https://github.com/rezafooladi2001/orbit.git

# 3. Push
git push -u origin main
```

