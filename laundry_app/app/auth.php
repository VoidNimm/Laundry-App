<?php
// app/auth.php
require_once __DIR__ . '/init.php';

function attempt_login($pdo, $username, $password) {
    $stmt = $pdo->prepare('SELECT * FROM tb_user WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) return false;
    if (password_verify($password, $user['password'])) {
        unset($user['password']);
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_admin() {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}
?>