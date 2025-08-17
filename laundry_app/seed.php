<?php
// seed.php - run once to create admin user
require_once __DIR__ . '/app/init.php';

$pdo->beginTransaction();
try {
    $count = $pdo->query('SELECT COUNT(*) FROM tb_user')->fetchColumn();
    if ($count > 0) {
        echo "Users exist, aborting seed.\n";
        $pdo->rollBack();
        exit;
    }
    $username = 'admin';
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO tb_user (nama, username, password, id_outlet, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Administrator', $username, $hash, 1, 'admin']);
    $pdo->commit();
    echo "Seed done. Admin: {$username} / {$password}\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Seed failed: " . $e->getMessage() . "\n";
}
?>