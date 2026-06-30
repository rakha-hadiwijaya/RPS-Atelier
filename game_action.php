<?php
require 'auth_check.php';
require 'db.php';

header('Content-Type: application/json');

$matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? 'heartbeat';
$userId = (int) $_SESSION['user_id'];

if (!$matchId) {
    $rawInput = file_get_contents('php://input');
    parse_str($rawInput, $parsedInput);

    $matchId = filter_var($parsedInput['match_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $parsedInput['action'] ?? $action;
}

if (!$matchId) {
    echo json_encode(['error' => 'Match tidak valid']);
    exit;
}

function nextRoundNumber(PDO $pdo, int $matchId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_rounds WHERE match_id=?");
    $stmt->execute([$matchId]);
    return (int) $stmt->fetchColumn() + 1;
}

function finishByForfeit(PDO $pdo, array $match, int $winnerId, string $reason): void
{
    if ($match['status'] === 'finished') {
        return;
    }

    $matchId = (int) $match['id'];
    $winnerIsP1 = (int) $winnerId === (int) $match['player1_id'];
    $roundNumber = nextRoundNumber($pdo, $matchId);
    $p1Choice = $match['player1_choice'] ?: ($winnerIsP1 ? 'menang' : $reason);
    $p2Choice = $match['player2_choice'] ?: ($winnerIsP1 ? $reason : 'menang');
    $p1Ms = $match['player1_response_ms'];
    $p2Ms = $match['player2_response_ms'];

    if (!$winnerIsP1 && $p2Ms === null) {
        $p2Ms = 10000;
    }

    if ($winnerIsP1 && $p1Ms === null) {
        $p1Ms = 10000;
    }

    if (!$match['player1_choice'] || !$match['player2_choice']) {
        $pdo->prepare("
            INSERT INTO match_rounds
                (match_id, round_number, player1_choice, player2_choice, winner, player1_response_ms, player2_response_ms, result_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$matchId, $roundNumber, $p1Choice, $p2Choice, $winnerId, $p1Ms, $p2Ms, $reason]);
    }

    $pdo->prepare("
        UPDATE matches
        SET
            winner_id=?,
            status='finished',
            finish_reason=?,
            player1_score=?,
            player2_score=?,
            player1_choice=NULL,
            player2_choice=NULL
        WHERE id=?
    ")->execute([
        $winnerId,
        $reason,
        $winnerIsP1 ? 2 : (int) $match['player1_score'],
        $winnerIsP1 ? (int) $match['player2_score'] : 2,
        $matchId,
    ]);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id=? FOR UPDATE");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();

    if (!$match) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Match tidak ditemukan']);
        exit;
    }

    if ((int) $match['player1_id'] === $userId) {
        $isP1 = true;
        $opponentId = (int) $match['player2_id'];
        $seenColumn = 'player1_last_seen_at';
    } elseif ((int) $match['player2_id'] === $userId) {
        $isP1 = false;
        $opponentId = (int) $match['player1_id'];
        $seenColumn = 'player2_last_seen_at';
    } else {
        $pdo->rollBack();
        echo json_encode(['error' => 'Kamu bukan player']);
        exit;
    }

    if ($match['status'] !== 'finished') {
        if ($action === 'forfeit') {
            finishByForfeit($pdo, $match, $opponentId, 'leave');
        } else {
            $pdo->prepare("UPDATE matches SET `$seenColumn`=NOW(3) WHERE id=?")
                ->execute([$matchId]);

            $p1Picked = !empty($match['player1_choice']);
            $p2Picked = !empty($match['player2_choice']);

            if ($p1Picked xor $p2Picked) {
                $firstMs = $p1Picked ? (int) $match['player1_response_ms'] : (int) $match['player2_response_ms'];
                $elapsedStmt = $pdo->prepare("
                    SELECT TIMESTAMPDIFF(MICROSECOND, current_round_started_at, NOW(3)) DIV 1000
                    FROM matches
                    WHERE id=?
                ");
                $elapsedStmt->execute([$matchId]);
                $elapsedMs = (int) $elapsedStmt->fetchColumn();

                if (($elapsedMs - $firstMs) >= 10000) {
                    $winnerId = $p1Picked ? (int) $match['player1_id'] : (int) $match['player2_id'];
                    finishByForfeit($pdo, $match, $winnerId, 'timeout');
                }
            }

            $opponentSeenColumn = $isP1 ? 'player2_last_seen_at' : 'player1_last_seen_at';
            if (!empty($match[$opponentSeenColumn])) {
                $seenStmt = $pdo->prepare("
                    SELECT TIMESTAMPDIFF(SECOND, `$opponentSeenColumn`, NOW(3))
                    FROM matches
                    WHERE id=?
                ");
                $seenStmt->execute([$matchId]);

                if ((int) $seenStmt->fetchColumn() >= 12) {
                    finishByForfeit($pdo, $match, $userId, 'disconnect');
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['error' => 'Gagal memproses status game']);
}
