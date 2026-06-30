<?php
require 'db.php';
session_start();

$user_id = $_SESSION['user_id'];

$pdo->prepare("UPDATE users SET is_online=0 WHERE id=?")
    ->execute([$user_id]);