<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

$dir = 'data/';
if (!is_dir($dir)) mkdir($dir, 0777, true);

$files = [
    'msgs' => $dir . 'messages.txt',
    'channels' => $dir . 'channels.json',
    'users' => $dir . 'users.json'
];

foreach ($files as $f) {
    if (!file_exists($f)) file_put_contents($f, pathinfo($f, PATHINFO_EXTENSION) === 'json' ? '{}' : '');
}

$action = $_GET['action'] ?? '';

function getData($key) {
    global $files;
    return json_decode(file_get_contents($files[$key]), true) ?? [];
}
function saveData($key, $data) {
    global $files;
    file_put_contents($files[$key], json_encode($data, JSON_PRETTY_PRINT));
}

if ($action === 'login') {
    $nick = htmlspecialchars(trim($_POST['nick'] ?? ''));
    $pass = $_POST['pass'] ?? '';

    if (strlen($nick) < 3) die(json_encode(['status' => 'error', 'msg' => 'İsim en az 3 karakter olmalı.']));

    // Config'deki isme göre şifre kontrolü
    if ($nick === ADMIN_NICK) {
        if ($pass !== ADMIN_PASS) {
            die(json_encode(['status' => 'error', 'msg' => 'Yetkili girişi için şifre gerekli!', 'require_pass' => true]));
        }
    }

    $users = getData('users');
    foreach ($users as $u) {
        if (isset($u['banned']) && $u['banned'] && $u['ip'] === $_SERVER['REMOTE_ADDR']) {
            die(json_encode(['status' => 'error', 'msg' => 'Bu sunucudan banlandınız.']));
        }
    }

    $_SESSION['user'] = $nick;
    $users[$nick] = [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'last_active' => time(),
        'banned' => ($users[$nick]['banned'] ?? false),
        'muted_until' => ($users[$nick]['muted_until'] ?? 0)
    ];
    saveData('users', $users);
    echo json_encode(['status' => 'success', 'nick' => $nick]);
}

elseif ($action === 'create_channel') {
    if (!isset($_SESSION['user']) || $_SESSION['user'] !== ADMIN_NICK) {
        die(json_encode(['status' => 'error', 'msg' => 'Sadece Admin kanal oluşturabilir.']));
    }
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $name = preg_replace('/[^a-zA-Z0-9-_]/', '', $name);
    if (!$name) die(json_encode(['status' => 'error', 'msg' => 'Geçersiz kanal adı.']));
    $channels = getData('channels');
    if (isset($channels[$name])) die(json_encode(['status' => 'error', 'msg' => 'Bu kanal zaten var.']));
    $channels[$name] = ['creator' => $_SESSION['user'], 'created_at' => time()];
    saveData('channels', $channels);
    echo json_encode(['status' => 'success']);
}

elseif ($action === 'send') {
    if (!isset($_SESSION['user'])) die(json_encode(['status' => 'error', 'msg' => 'Giriş yapın.']));
    $user = $_SESSION['user'];
    $text = htmlspecialchars($_POST['text'] ?? '');
    $channel = $_POST['channel'] ?? 'Genel';
    $users = getData('users');

    // MUTE KONTROLÜ DÜZELTİLDİ
    if (isset($users[$user]) && $users[$user]['muted_until'] > time()) {
        $kalan = $users[$user]['muted_until'] - time();
        die(json_encode(['status' => 'error', 'msg' => "Susturuldunuz. Kalan: $kalan sn"]));
    }
    
    if (strpos($text, '/') === 0) {
        if ($user !== ADMIN_NICK) die(json_encode(['status' => 'error', 'msg' => 'Yetkiniz yok.']));
        $parts = explode(' ', substr($text, 1));
        $cmd = $parts[0];
        $target = $parts[1] ?? '';
        $arg = $parts[2] ?? 0;

        if ($cmd === 'ban' && isset($users[$target])) {
            $users[$target]['banned'] = true;
            saveData('users', $users);
            $text = "<i>[Sistem]: $target banlandı.</i>";
        } elseif ($cmd === 'mute' && isset($users[$target])) {
            $users[$target]['muted_until'] = time() + (int)$arg;
            saveData('users', $users);
            $text = "<i>[Sistem]: $target $arg saniye susturuldu.</i>";
        }
    }

    $entry = ['id' => uniqid(), 'time' => date('H:i:s'), 'user' => $user, 'text' => $text, 'channel' => $channel, 'color' => ($user === ADMIN_NICK ? '#aa0000' : '#000')];
    file_put_contents($files['msgs'], json_encode($entry) . PHP_EOL, FILE_APPEND);
    $users[$user]['last_active'] = time();
    saveData('users', $users);
    echo json_encode(['status' => 'success']);
}

elseif ($action === 'read') {
    $currentChannel = $_GET['channel'] ?? 'Genel';
    $lines = file($files['msgs'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $messages = [];
    if ($lines) {
        foreach (array_slice($lines, -100) as $line) {
            $data = json_decode($line, true);
            if ($data && $data['channel'] === $currentChannel) $messages[] = $data;
        }
    }
    $allUsers = getData('users');
    $onlineUsers = [];
    foreach ($allUsers as $uName => $uData) {
        if ((time() - $uData['last_active']) < 60 && !$uData['banned']) {
            $onlineUsers[] = ['name' => $uName, 'role' => ($uName === ADMIN_NICK ? 'admin' : 'user')];
        }
    }
    $channels = getData('channels');
    if (empty($channels)) { $channels['Genel'] = ['creator' => 'Sistem']; saveData('channels', $channels); }
    echo json_encode(['messages' => $messages, 'users' => $onlineUsers, 'channels' => array_keys($channels)]);
}
?>