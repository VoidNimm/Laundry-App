<?php
// app/init.php - database connection and session
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'laundry_fixed');
define('DB_USER', 'root');
define('DB_PASS', '');

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, $options);
} catch (Exception $e) {
    die('DB connection error: ' . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: /login.php');
        exit;
    }
}

function app_log($pdo, $user_id, $action, $details='') {
    // simple logging helper - creates logs table if not exists (non-critical)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(100), details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");    
    } catch (Exception $e) { /* ignore */ }
    $stmt = $pdo->prepare('INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $action, $details]);
}
?>