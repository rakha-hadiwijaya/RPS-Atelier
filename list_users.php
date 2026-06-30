<?php
require 'db.php';

header('Content-Type: application/json');

$users = $pdo->query("SELECT username, is_online FROM users ORDER BY is_online DESC")
    ->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($users);