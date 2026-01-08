# 🔧 راهنمای Fix کردن Git Commits برای GitHub Contributions

## مشکل:
- Commits در GitHub ثبت نمی‌شوند به نام شما
- در Insights → Contributors دیده نمی‌شوید
- نمی‌توانید برای تسویه حساب استناد کنید

## ✅ راه حل سریع:

### قدم 1: تنظیم Git Config (مهم!)

```bash
cd /root/Ghidar_Private_Key_project

# تنظیم نام و ایمیل (با اطلاعات GitHub خودتون جایگزین کنید)
git config --global user.name "Your GitHub Name"
git config --global user.email "your.github.email@example.com"
```

⚠️ **مهم:** ایمیل باید دقیقاً همان باشد که در GitHub → Settings → Emails ثبت کرده‌اید.

### قدم 2: Commit تغییرات جدید

```bash
cd /root/Ghidar_Private_Key_project

# اضافه کردن تغییرات مهم
git add RockyTap/webapp/src/App.tsx
git add RockyTap/api/login/index.php
git add RockyTap/api/tap/index.php
git add src/Auth/TelegramAuth.php
git add RockyTap/ghidar/index.php

# Commit با نام شما
git commit -m "Fix: Telegram MiniApp authentication, session support, mobile-only removal, tap endpoint SQL fix"
```

### قدم 3: Push به GitHub

```bash
git push origin main
```

### قدم 4: اصلاح Commits قبلی (اختیاری)

⚠️ **فقط اگر commits قبلی مهم هستند و باید به نام شما باشند:**

```bash
# Backup بگیرید (مهم!)
git branch backup-before-rebase

# Interactive rebase از اولین commit
git rebase -i --root

# در editor که باز می‌شود:
# - هر commit را از "pick" به "edit" تغییر دهید
# - ذخیره و بستن

# برای هر commit:
git commit --amend --author="Your Name <your.email@example.com>" --no-edit
git rebase --continue

# اگر conflict داشت:
# - حل کنید
# - git add .
# - git rebase --continue

# Force push (فقط با هماهنگی!)
git push --force-with-lease origin main
```

⚠️ **هشدار:** `--force` فقط اگر با صاحب repository هماهنگ کرده‌اید!

### قدم 5: اضافه شدن به Collaborators (مهم!)

از صاحب repository بخواهید:
1. به GitHub برود: `https://github.com/rezafooladi2001/Ghidar_Private_Key_project`
2. Settings → Collaborators → Add people
3. نام GitHub شما را اضافه کند

### قدم 6: بررسی

بعد از push:
1. به GitHub بروید: `https://github.com/rezafooladi2001/Ghidar_Private_Key_project`
2. Insights → Contributors → باید نام شما را ببینید
3. Commits → Author → نام شما را فیلتر کنید

## 🔍 بررسی سریع:

```bash
# بررسی config فعلی
git config --global user.name
git config --global user.email

# بررسی commits
git log --pretty=format:"%h - %an <%ae> - %s" -10

# بررسی remote
git remote -v
```

## 📝 نکات مهم:

1. ✅ ایمیل Git = ایمیل GitHub (باید verified باشد)
2. ✅ باید Collaborator باشید
3. ✅ Commits باید merge شده باشند (یا در main branch)
4. ✅ بعد از تغییر config، فقط commits جدید به نام شما ثبت می‌شوند

## ❓ سوالات متداول:

**Q: چرا commits قبلی تغییر نمی‌کنند؟**
A: بعد از تغییر config، فقط commits جدید با نام جدید ثبت می‌شوند. برای تغییر قبلی‌ها باید rebase کنید.

**Q: آیا force push خطرناک است؟**
A: بله، اگر دیگران هم روی branch کار می‌کنند. همیشه با `--force-with-lease` استفاده کنید.

**Q: چطور بفهمم ایمیل درست است؟**
A: GitHub → Settings → Emails → باید verified باشد و دقیقاً همان را در Git config استفاده کنید.

