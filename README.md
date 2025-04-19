# pwdgenbot

**Простой Telegram-бот на PHP для генерации безопасных паролей.**

- Без фреймворков, чистый PHP
- Генерация паролей по шаблону (например `12A2s2`)
- Кнопки для быстрой генерации: простой, сложный, сверхсложный
- Ограничение на 1000 запросов в сутки на пользователя
- Поддержка Telegram Premium, языков, отчётов для администратора
- Исходный код открыт и может быть развёрнут на любом сервере

## Использование

1. Настройте токен в `passwordbot_config.php`
2. Создайте таблицу:

```sql
CREATE TABLE `user_limits` (
    `user_id` BIGINT PRIMARY KEY,
    `date` DATE NOT NULL,
    `count` INT NOT NULL,
    `lang` VARCHAR(10),
    `is_premium` TINYINT
);
```

3. Установите webhook на ваш сервер:

```bash
curl -F "url=https://yourdomain.com/passwordbot.php" https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook
```

## Автор

[telema93](https://github.com/telema93)