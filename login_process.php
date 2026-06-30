<?php
session_start();
require 'db.php';

$username = $_POST['username'];
$password = $_POST['password'];

// ambil user
$stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
$stmt->execute([$username]);

$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {

    // login sukses
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    header("Location: chat.php");
    exit;

} else {

    // simpan error
    $_SESSION['login_error'] = "Username atau password salah";

    header("Location: login.php");
    exit;
}