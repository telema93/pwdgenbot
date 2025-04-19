<?php

$config = require 'passwordbot_config.php';
$token = $config['bot_token'];
$api = "https://api.telegram.org/bot$token";

require_once 'passwordbot_db.php';

// === Получение update и логирование
$update = json_decode(file_get_contents("php://input"), true);
file_put_contents(__DIR__ . "/logs.txt", json_encode($update, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n", FILE_APPEND);

// === Определение переменных
$callback = $update['callback_query'] ?? null;
$message_obj = $update['message'] ?? null;
$message = $message_obj['text'] ?? '';
$chat_id = $message_obj['chat']['id'] ?? null;
$user_id = $message_obj['from']['id'] ?? null;

// === Обработка callback_query
if ($callback) {
    $chat_id = $callback['message']['chat']['id'] ?? null;
    $user_id = $callback['from']['id'] ?? null;
    $data = $callback['data'] ?? '';

    if (!check_limit($user_id)) {
        sendMessage($chat_id, "⛔ Превышен лимит сообщений. Максимум 1000 в сутки.");
        exit;
    }

    if ($data === 'simple') {
        $password = generate_simple_password(12);
    } elseif ($data === 'normal') {
        $password = generate_password(12, ['A'=>2,'l'=>2,'d'=>2,'s'=>2]);
    } elseif ($data === 'strong') {
        $password = generate_password(20, ['A'=>3,'l'=>3,'d'=>3,'s'=>3]);
    } elseif ($data === 'admin_report' && $user_id == $config['admin_id']) {
        $report = get_admin_report();
        answerCallback($callback['id']);
        sendMessage($chat_id, $report);
        exit;
    } else {
        $password = '❓ Неизвестная команда.';
    }

    answerCallback($callback['id']);
    sendMessage($chat_id, "<pre>$password</pre>", "HTML");
    exit;
}

// === Обработка обычного сообщения
if (!$message && isset($update['message']['entities'])) {
    sendIntro($chat_id);
    exit;
}

if (!$user_id || !$chat_id || !check_limit($user_id)) {
    sendMessage($chat_id, "⛔ Превышен лимит сообщений. Максимум 1000 в сутки.");
    exit;
}

// Обновляем данные о пользователе
$lang = $message_obj['from']['language_code'] ?? null;
$is_premium = $message_obj['from']['is_premium'] ?? null;
update_user_info($user_id, $lang, $is_premium ? 1 : 0);

// Если группа — игнорируем
if ($message_obj['chat']['type'] !== 'private') {
    exit;
}

// === Парсинг шаблона
preg_match('/(\d+)([Alds\d]*)/i', $message, $matches);
if (!isset($matches[1])) {
    sendIntro($chat_id);
    exit;
}

$length = (int)$matches[1];
$options_str = $matches[2] ?? '';
$requirements = ['A' => 0, 'l' => 0, 'd' => 0, 's' => 0];

preg_match_all('/([Alds])(\d*)/i', $options_str, $opt_matches, PREG_SET_ORDER);
foreach ($opt_matches as $opt) {
    $type = strtoupper($opt[1]);
    $count = isset($opt[2]) && $opt[2] !== '' ? (int)$opt[2] : 1;
    if (isset($requirements[$type])) {
        $requirements[$type] = $count;
    }
}

if (array_sum($requirements) === 0) {
    $length = 12;
    $requirements = ['A' => 2, 'l' => 2, 'd' => 2, 's' => 2];
}

$password = generate_password($length, $requirements);
sendMessage($chat_id, "<pre>$password</pre>", "HTML");

// === Функции ===

function generate_password($length, $req) {
    $sets = [
        'A' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'l' => 'abcdefghijklmnopqrstuvwxyz',
        'd' => '0123456789',
        's' => '!@#$%^&*()-_=+[]{}<>?',
    ];

    $required = '';
    foreach ($req as $type => $min) {
        for ($i = 0; $i < $min; $i++) {
            $required .= $sets[$type][random_int(0, strlen($sets[$type]) - 1)];
        }
    }

    $remaining = $length - strlen($required);
    if ($remaining < 0) return 'Ошибка: слишком много обязательных символов.';

    $all = implode('', $sets);
    for ($i = 0; $i < $remaining; $i++) {
        $required .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($required);
}

function generate_simple_password($length) {
    $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $digits = '0123456789';

    $first = $letters[random_int(0, strlen($letters) - 1)];
    $rest = '';
    for ($i = 1; $i < $length; $i++) {
        $set = $letters . $digits;
        $rest .= $set[random_int(0, strlen($set) - 1)];
    }
    return $first . $rest;
}

function sendMessage($chat_id, $text, $mode = "Markdown") {
    global $api;
    file_get_contents("$api/sendMessage?" . http_build_query([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $mode,
        'disable_web_page_preview' => true                                                     
    ]));
}

function sendButtons($chat_id, $text = "Выберите тип пароля:") {
    global $api, $config;

    $buttons = [[
        ['text' => 'Простой 🔑', 'callback_data' => 'simple'],
        ['text' => 'Сложный 🧠', 'callback_data' => 'normal'],
        ['text' => 'Сверхсложный 🧬', 'callback_data' => 'strong']
    ]];

    if ($chat_id == $config['admin_id']) {
        $buttons[] = [['text' => '📊 Отчёт', 'callback_data' => 'admin_report']];
    }

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        'parse_mode' => 'Markdown'
    ];
    file_get_contents($api . "/sendMessage?" . http_build_query($data));
}

function answerCallback($callback_id) {
    global $api;
    file_get_contents($api . "/answerCallbackQuery?callback_query_id=$callback_id");
}

function sendIntro($chat_id) {
    $text = <<<TXT
👋 Добро пожаловать в генератор паролей!

Вы можете отправить команду вида: `12A2s2` — это значит:
- 12 символов
- минимум 2 заглавных (A2)
- минимум 2 спецсимвола (s2)

Или просто нажмите одну из кнопок ниже.

ℹ️ Мы не сохраняем сгенерированные пароли. Повторить сгенерированный пароль нельзя.
💻 Исходный код бота: [GitHub](https://github.com/telema93/pwdgenbot)
📵 Бот работает только в личных сообщениях.
TXT;

    sendButtons($chat_id, $text);
}
