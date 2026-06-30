<?php
require 'auth_check.php';
require 'db.php';

$match_id = $_GET['match_id'] ?? null;

if (!$match_id) {
    echo json_encode(null);
    exit;
}

// ambil match utama
$stmt = $pdo->prepare("SELECT * FROM matches WHERE id=?");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    echo json_encode(null);
    exit;
}

// 🔥 ambil round terakhir
$stmt = $pdo->prepare("
    SELECT * FROM match_rounds
    WHERE match_id=?
    ORDER BY round_number DESC
    LIMIT 1
");
$stmt->execute([$match_id]);
$lastRound = $stmt->fetch(PDO::FETCH_ASSOC);

// 🔥 ambil semua round (buat nanti halaman statistik)
$stmt = $pdo->prepare("
    SELECT round_number, player1_choice, player2_choice, winner
    FROM match_rounds
    WHERE match_id=?
    ORDER BY round_number ASC
");
$stmt->execute([$match_id]);
$rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$timeoutRemaining = null;
if (($match['player1_choice'] && !$match['player2_choice']) || (!$match['player1_choice'] && $match['player2_choice'])) {
    $firstMs = $match['player1_choice']
        ? (int) $match['player1_response_ms']
        : (int) $match['player2_response_ms'];

    $stmt = $pdo->prepare("
        SELECT TIMESTAMPDIFF(MICROSECOND, current_round_started_at, NOW(3)) DIV 1000
        FROM matches
        WHERE id=?
    ");
    $stmt->execute([$match_id]);
    $elapsedMs = (int) $stmt->fetchColumn();
    $timeoutRemaining = max(0, 10 - (($elapsedMs - $firstMs) / 1000));
}

echo json_encode([
    // choice sekarang (round berjalan)
    'p1' => $match['player1_choice'],
    'p2' => $match['player2_choice'],

    // player
    'player1_id' => $match['player1_id'],
    'player2_id' => $match['player2_id'],

    // 🔥 score BO3
    'player1_score' => $match['player1_score'],
    'player2_score' => $match['player2_score'],

    // 🔥 status match
    'status' => $match['status'],
    'winner_id' => $match['winner_id'],
    'finish_reason' => $match['finish_reason'],
    'player1_response_ms' => $match['player1_response_ms'],
    'player2_response_ms' => $match['player2_response_ms'],
    'timeout_remaining' => $timeoutRemaining,

    // 🔥 round terakhir
    'last_round' => $lastRound,

    // 🔥 semua round (buat statistik nanti)
    'rounds' => $rounds
]);
