<?php
require 'db.php';

$username = $_POST['username'];
$password = $_POST['password'];

// 🔥 hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// cek username udah ada belum
$stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
$stmt->execute([$username]);

if ($stmt->rowCount() > 0) {
    die("Username sudah dipakai 😢");
}

// insert user
$stmt = $pdo->prepare("
    INSERT INTO users (username, password, points, streak, is_online)
    VALUES (?, ?, 0, 0, 0)
");
$stmt->execute([$username, $hashed]);

header("Location: login.php");
exit;