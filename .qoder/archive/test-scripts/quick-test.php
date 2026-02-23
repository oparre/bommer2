<?php
require_once __DIR__ . '/config/database.php';

$pdo = getDb();
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
$stmt->execute();
$user = $stmt->fetch();

$test = password_verify('Admin@123', $user['password_hash']);
echo $test ? "PASS - Login will work!\n" : "FAIL - Login will not work\n";
