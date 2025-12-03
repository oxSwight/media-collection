# Настройка отправки email для восстановления пароля

MediaLib поддерживает несколько способов отправки email для восстановления пароля.

## Вариант 1: SendGrid (Рекомендуется для Render)

**Преимущества:** Простой API, бесплатный тариф до 100 писем/день, работает на Render.

### Шаги:

1. Зарегистрируйтесь на [SendGrid](https://sendgrid.com/) (бесплатный аккаунт).

2. **ВАЖНО: Верифицируйте отправителя (Sender Identity):**
   - Зайдите в SendGrid Dashboard
   - Settings → **Sender Authentication**
   - Выберите **"Verify a Single Sender"** (проще всего для начала)
   - Заполните форму:
     - **From Email Address:** ваш реальный email (например, `twoj-email@gmail.com`)
     - **From Name:** MediaLib (или любое имя)
     - Остальные поля заполните по желанию
   - Нажмите **"Create"**
   - **Проверьте почту** - SendGrid отправит письмо с подтверждением
   - **Кликните на ссылку в письме** для верификации
   - После верификации статус изменится на "Verified" ✅

3. Создайте API Key:
   - Settings → **API Keys** → **Create API Key**
   - Дайте имя (например, "MediaLib")
   - Выберите **"Full Access"** или **"Restricted Access"** с правами на отправку писем
   - **Скопируйте ключ** (он показывается только один раз!)

4. На Render в настройках вашего Web Service:
   - Settings → **Environment**
   - Добавьте переменные:
     ```
     SENDGRID_API_KEY = ваш_ключ_из_шага_3
     SENDGRID_FROM_EMAIL = тот_же_email_что_верифицировали_в_шаге_2
     SENDGRID_FROM_NAME = MediaLib
     ```
   - **Важно:** `SENDGRID_FROM_EMAIL` должен совпадать с верифицированным email из шага 2!

5. Сохраните и перезапустите сервис (Redeploy).

### Если видите ошибку "verified Sender Identity":

Это значит, что email в `SENDGRID_FROM_EMAIL` не верифицирован в SendGrid. Решение:
- Вернитесь к шагу 2 и верифицируйте email
- Убедитесь, что `SENDGRID_FROM_EMAIL` в Render точно совпадает с верифицированным email

---

## Вариант 2: Gmail SMTP

**Преимущества:** Бесплатно, если у вас есть Gmail аккаунт.

### Шаги:

1. Включите "Менее безопасные приложения" в Gmail (или используйте App Password):
   - Google Account → Security → 2-Step Verification (включите)
   - App Passwords → Generate → выберите "Mail" и "Other"
   - Скопируйте сгенерированный пароль (16 символов)
2. На Render добавьте переменные окружения:
   ```
   SMTP_HOST = smtp.gmail.com
   SMTP_PORT = 587
   SMTP_USER = ваш-email@gmail.com
   SMTP_PASS = ваш_app_password_из_шага_1
   SMTP_FROM = ваш-email@gmail.com
   SMTP_FROM_NAME = MediaLib
   ```
3. Сохраните и перезапустите.

---

## Вариант 3: Другие SMTP провайдеры

Поддерживаются любые SMTP серверы. Примеры:

**Mailgun:**
```
SMTP_HOST = smtp.mailgun.org
SMTP_PORT = 587
SMTP_USER = postmaster@ваш-домен.mailgun.org
SMTP_PASS = ваш_пароль_mailgun
```

**Outlook/Hotmail:**
```
SMTP_HOST = smtp-mail.outlook.com
SMTP_PORT = 587
SMTP_USER = ваш-email@outlook.com
SMTP_PASS = ваш_пароль
```

---

## Проверка работы

1. Зайдите на сайт → "Забыл пароль"
2. Введите email зарегистрированного пользователя
3. Нажмите "Отправить"
4. Проверьте почту (включая папку "Спам")
5. Если письмо не пришло, проверьте логи на Render или переменные окружения

---

## Режим разработки

Если переменные окружения не заданы, система попытается использовать встроенный `mail()` PHP. На Render это обычно не работает, поэтому на localhost вы увидите ссылку прямо на странице (для тестирования).

---

## Безопасность

- Токены для сброса пароля действительны только 1 час
- После использования токен удаляется из базы
- Ссылки содержат случайный 32-символьный токен, который невозможно угадать

