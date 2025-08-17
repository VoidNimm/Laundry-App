<?php
require_once __DIR__ . '/../app/init.php';
session_destroy();
header('Location: login.php');
exit;
?>