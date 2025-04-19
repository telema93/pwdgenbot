<?php

function get_db() {
    $config = require 'passwordbot_config.php';
    $db = $config['db'];
    return new PDO("mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8mb4", $db['user'], $db['pass']);
}

function check_limit($user_id, $limit = 1000) {
    $pdo = get_db();
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT `count`, `date` FROM `user_limits` WHERE `user_id` = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $stmt = $pdo->prepare("INSERT INTO `user_limits` (`user_id`, `date`, `count`) VALUES (?, ?, 1)");
        $stmt->execute([$user_id, $today]);
        return true;
    }

    if ($row['date'] !== $today) {
        $stmt = $pdo->prepare("UPDATE `user_limits` SET `date` = ?, `count` = 1 WHERE `user_id` = ?");
        $stmt->execute([$today, $user_id]);
        return true;
    }

    if ($row['count'] >= $limit) return false;

    $stmt = $pdo->prepare("UPDATE `user_limits` SET `count` = `count` + 1 WHERE `user_id` = ?");
    $stmt->execute([$user_id]);
    return true;
}

function update_user_info($user_id, $lang, $is_premium) {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE `user_limits` SET `lang` = ?, `is_premium` = ? WHERE `user_id` = ?");
    $stmt->execute([$lang, $is_premium, $user_id]);
}

function get_admin_report() {
    $pdo = get_db();
    $today = date('Y-m-d');

    $total = $pdo->query("SELECT COUNT(*) FROM `user_limits`")->fetchColumn();
    $today_count = $pdo->query("SELECT SUM(`count`) FROM `user_limits` WHERE `date` = '$today'")->fetchColumn();
    $lang_stats = $pdo->query("SELECT `lang`, COUNT(*) AS c FROM `user_limits` GROUP BY `lang`")->fetchAll(PDO::FETCH_ASSOC);
    $premium_stats = $pdo->query("SELECT `is_premium`, COUNT(*) AS c FROM `user_limits` GROUP BY `is_premium`")->fetchAll(PDO::FETCH_ASSOC);
    $top_users = $pdo->query("SELECT `user_id`, `count` FROM `user_limits` ORDER BY `count` DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $text = "📊 Статистика:
";
    $text .= "👥 Пользователей всего: $total
";
    $text .= "📨 Запросов сегодня: $today_count

";

    $text .= "🌍 Языки:
";
    foreach ($lang_stats as $row) {
        $lang = $row['lang'] ?: 'неизвестно';
        $text .= "- $lang: {$row['c']}
";
    }

    $text .= "\n💎 Telegram Premium:
";
    foreach ($premium_stats as $row) {
        $label = $row['is_premium'] == 1 ? 'Да' : ($row['is_premium'] === '0' ? 'Нет' : 'неизвестно');
        $text .= "- $label: {$row['c']}
";
    }

    $text .= "\n🔥 Топ пользователей:
";
    foreach ($top_users as $row) {
        $text .= "- ID {$row['user_id']}: {$row['count']} запросов
";
    }

    return $text;
}