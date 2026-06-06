<?php

$_['heading_title'] = 'OCM Webhook';

$_['text_extension'] = 'Розширення';
$_['text_home'] = 'Головна';
$_['text_success'] = 'Налаштування webhook успішно збережено!';
$_['text_edit'] = 'Налаштування OCM Webhook';
$_['text_enabled'] = 'Увімкнено';
$_['text_disabled'] = 'Вимкнено';

$_['entry_status'] = 'Статус модуля';
$_['entry_log_status'] = 'Звичайне логування';
$_['entry_rules'] = 'Правила webhook';
$_['entry_event'] = 'Подія OpenCart';
$_['entry_url'] = 'URL webhook';
$_['entry_sort_order'] = 'Порядок виконання';
$_['entry_auth'] = 'Авторизація';
$_['entry_auth_type'] = 'Метод авторизації';
$_['entry_auth_token'] = 'Токен / секрет';
$_['entry_auth_username'] = 'Логін Basic Auth';
$_['entry_auth_password'] = 'Пароль Basic Auth';

$_['help_rules'] = 'Додайте стільки правил, скільки потрібно. Кожне правило реєструється як окрема подія OpenCart. Для максимально свіжих даних ставте події /after і великий порядок виконання, наприклад 1000.';
$_['help_event'] = 'Trigger події OpenCart, наприклад `catalog/model/checkout/order/addOrder/after`. Підтримуються також `admin/`, `controller/`, `model/`, `view/`, `language/` та `config/` події.';
$_['help_url'] = 'Абсолютний URL для POST webhook. Якщо запит впаде, помилка потрапить у `ocm_webhook.log`, а OpenCart продовжить роботу.';
$_['help_sort_order'] = 'Чим більше число, тим пізніше подія виконається. Якщо потрібно отримати вже оновлені дані, ставте 1000 або більше.';
$_['help_auth'] = 'Опційно. Залишайте вимкненим, якщо цільовий webhook не потребує авторизації.';
$_['help_auth_token'] = 'Використовується для режимів Query Token, Bearer Token, Custom Header Token та HMAC SHA256. Можна згенерувати спільний секрет автоматично.';
$_['help_auth_basic'] = 'Використовується тільки для Basic Auth. Логін і пароль передаються в заголовку Authorization.';
$_['help_log_status'] = 'Вимкніть це, щоб зупинити інформаційні та попереджувальні логи. Логи помилок завжди записуються.';
$_['button_generate_token'] = 'Згенерувати токен';
$_['text_auth_none'] = 'Без авторизації';
$_['text_auth_query'] = 'Токен у query';
$_['text_auth_bearer'] = 'Bearer токен';
$_['text_auth_header'] = 'Токен у заголовку';
$_['text_auth_basic'] = 'Basic auth';
$_['text_auth_hmac'] = 'HMAC SHA256';

$_['button_save'] = 'Зберегти';
$_['button_cancel'] = 'Скасувати';
$_['button_add_rule'] = 'Додати правило';
$_['button_remove'] = 'Видалити';

$_['error_permission'] = 'Увага: у вас немає прав для зміни цього модуля!';
