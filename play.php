<?php
require 'auth_check.php';
require 'db.php';

header('Content-Type: application/json');

$match_id = $_POST['match_id'] ?? null;
$user_id = $_SESSION['user_id'];
$choice = $_POST['choice'] ?? null;

if (!$match_id || !in_array($choice, ['batu', 'kertas', 'gunting'], true)) {
    echo json_encode(['error' => 'Data tidak lengkap']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id=? FOR UPDATE");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();

    if (!$match) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Match tidak ditemukan']);
        exit;
    }

    if ($match['status'] === 'finished') {
        $pdo->commit();
        echo json_encode(['finished' => true]);
        exit;
    }

    if ((int) $match['player1_id'] === (int) $user_id) {
        $isP1 = true;
    } elseif ((int) $match['player2_id'] === (int) $user_id) {
        $isP1 = false;
    } else {
        $pdo->rollBack();
        echo json_encode(['error' => 'Kamu bukan player di match ini']);
        exit;
    }

    if ($isP1 && $match['player1_choice']) {
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    if (!$isP1 && $match['player2_choice']) {
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($isP1) {
        $pdo->prepare("
            UPDATE matches
            SET
                player1_choice=?,
                player1_response_ms=TIMESTAMPDIFF(MICROSECOND, current_round_started_at, NOW(3)) DIV 1000
            WHERE id=?
        ")
            ->execute([$choice, $match_id]);
    } else {
        $pdo->prepare("
            UPDATE matches
            SET
                player2_choice=?,
                player2_response_ms=TIMESTAMPDIFF(MICROSECOND, current_round_started_at, NOW(3)) DIV 1000
            WHERE id=?
        ")
            ->execute([$choice, $match_id]);
    }

    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id=?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();

    $p1 = $match['player1_choice'];
    $p2 = $match['player2_choice'];

    if ($p1 && $p2) {
        $winner = null;

        if ($p1 !== $p2) {
            $player1Wins =
                ($p1 === 'batu' && $p2 === 'gunting') ||
                ($p1 === 'gunting' && $p2 === 'kertas') ||
                ($p1 === 'kertas' && $p2 === 'batu');

            $winner = $player1Wins ? $match['player1_id'] : $match['player2_id'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_rounds WHERE match_id=?");
        $stmt->execute([$match_id]);
        $roundNumber = (int) $stmt->fetchColumn() + 1;

        $pdo->prepare("
            INSERT INTO match_rounds
                (match_id, round_number, player1_choice, player2_choice, winner, player1_response_ms, player2_response_ms, result_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'normal')
        ")->execute([
            $match_id,
            $roundNumber,
            $p1,
            $p2,
            $winner,
            $match['player1_response_ms'],
            $match['player2_response_ms'],
        ]);

        if ((int) $winner === (int) $match['player1_id']) {
            $pdo->prepare("UPDATE matches SET player1_score = player1_score + 1 WHERE id=?")
                ->execute([$match_id]);
        }

        if ((int) $winner === (int) $match['player2_id']) {
            $pdo->prepare("UPDATE matches SET player2_score = player2_score + 1 WHERE id=?")
                ->execute([$match_id]);
        }

        $pdo->prepare("
            UPDATE matches
            SET
                player1_choice=NULL,
                player2_choice=NULL,
                player1_response_ms=NULL,
                player2_response_ms=NULL,
                current_round_started_at=NOW(3)
            WHERE id=?
        ")->execute([$match_id]);

        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id=?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch();

        if ((int) $match['player1_score'] >= 2 || (int) $match['player2_score'] >= 2) {
            $winnerId = (int) $match['player1_score'] >= 2
                ? $match['player1_id']
                : $match['player2_id'];

            $pdo->prepare("
                UPDATE matches
                SET winner_id=?, status='finished'
                WHERE id=?
            ")->execute([$winnerId, $match_id]);

            $pdo->commit();

            echo json_encode([
                'finished' => true,
                'winner' => $winnerId,
            ]);
            exit;
        }

        $pdo->commit();

        echo json_encode(['round_complete' => true]);
        exit;
    }

    $pdo->commit();

    echo json_encode(['waiting' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['error' => 'Gagal memproses pilihan']);
}
