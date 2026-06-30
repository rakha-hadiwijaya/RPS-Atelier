<?php
session_start();
require 'db.php';

$pdo->prepare("UPDATE users SET is_online=0 WHERE id=?")
    ->execute([$_SESSION['user_id']]);

session_destroy();
header("Location: login.php");