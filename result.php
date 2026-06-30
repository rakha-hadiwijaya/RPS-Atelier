<?php
require 'auth_check.php';
require 'db.php';

$match_id = filter_input(INPUT_GET, 'match_id', FILTER_VALIDATE_INT);
$user_id = (int) $_SESSION['user_id'];

if (!$match_id) {
    die('Match tidak ditemukan');
}

$stmt = $pdo->prepare("
    SELECT
        m.*,
        p1.username AS player1_name,
        p2.username AS player2_name
    FROM matches m
    LEFT JOIN users p1 ON p1.id = m.player1_id
    LEFT JOIN users p2 ON p2.id = m.player2_id
    WHERE m.id=?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    die('Match tidak ditemukan');
}

if ((int) $match['player1_id'] !== $user_id && (int) $match['player2_id'] !== $user_id) {
    http_response_code(403);
    die('Kamu tidak punya akses ke hasil match ini');
}

$roundsQuery = $pdo->prepare("
    SELECT * FROM match_rounds
    WHERE match_id=?
    ORDER BY round_number ASC
");
$roundsQuery->execute([$match_id]);
$rounds = $roundsQuery->fetchAll();

$winner = $match['winner_id'] ? (int) $match['winner_id'] : null;
$choiceColumns = [
    'batu' => 'rock_count',
    'kertas' => 'paper_count',
    'gunting' => 'scissors_count',
];

function recordRatingChange(PDO $pdo, int $userId, int $oldPoints, int $newPoints, int $matchId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO rating_history (user_id, old_points, new_points, match_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $oldPoints, $newPoints, $matchId]);
}

if ($match['status'] === 'finished' && (int) $match['processed'] === 0) {
    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare("
            UPDATE matches
            SET processed = 1
            WHERE id=? AND processed=0
        ");
        $lock->execute([$match_id]);

        if ($lock->rowCount() > 0) {
            if ($winner) {
                $loser = (int) $winner === (int) $match['player1_id']
                    ? (int) $match['player2_id']
                    : (int) $match['player1_id'];

                $pointsStmt = $pdo->prepare("
                    SELECT id, points
                    FROM users
                    WHERE id IN (?, ?)
                    FOR UPDATE
                ");
                $pointsStmt->execute([$winner, $loser]);
                $pointsBefore = [];

                foreach ($pointsStmt->fetchAll() as $user) {
                    $pointsBefore[(int) $user['id']] = (int) $user['points'];
                }

                $pdo->prepare("
                    UPDATE users
                    SET
                        points = points + 10,
                        streak = streak + 1,
                        total_win = total_win + 1,
                        total_match = total_match + 1
                    WHERE id=?
                ")->execute([$winner]);

                $pdo->prepare("
                    UPDATE users
                    SET
                        points = GREATEST(points - 5, 0),
                        streak = 0,
                        total_lose = total_lose + 1,
                        total_match = total_match + 1
                    WHERE id=?
                ")->execute([$loser]);

                $winnerOldPoints = $pointsBefore[(int) $winner] ?? 0;
                $loserOldPoints = $pointsBefore[(int) $loser] ?? 0;

                recordRatingChange($pdo, (int) $winner, $winnerOldPoints, $winnerOldPoints + 10, $match_id);
                recordRatingChange($pdo, (int) $loser, $loserOldPoints, max($loserOldPoints - 5, 0), $match_id);
            } else {
                $pdo->prepare("
                    UPDATE users
                    SET
                        total_draw = total_draw + 1,
                        total_match = total_match + 1
                    WHERE id IN (?, ?)
                ")->execute([$match['player1_id'], $match['player2_id']]);
            }

            foreach ($rounds as $round) {
                foreach ([
                    'player1_choice' => $match['player1_id'],
                    'player2_choice' => $match['player2_id'],
                ] as $choiceField => $playerId) {
                    $choice = $round[$choiceField] ?? null;

                    if (!$choice || !isset($choiceColumns[$choice])) {
                        continue;
                    }

                    $column = $choiceColumns[$choice];
                    $pdo->prepare("
                        UPDATE users
                        SET `$column` = `$column` + 1
                        WHERE id=?
                    ")->execute([$playerId]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        die('Gagal memproses hasil match');
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '-', ENT_QUOTES, 'UTF-8');
}

function choiceLabel(?string $choice): string
{
    return strtoupper($choice ?: '-');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Match Result</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap');

        :root{
            --green:#5fba7d;
            --green-bg:rgba(45,107,63,0.2);
            --red:#e07a6a;
            --red-bg:rgba(192,57,43,0.15);
            --gold:#c9a84c;
            --gold-light:#e8d48b;
            --text-primary:#f0ead6;
            --text-muted:rgba(240,234,214,0.6);
        }

        body{
            background:
                radial-gradient(ellipse at 20% 50%, rgba(30,77,43,0.4) 0%, transparent 60%),
                linear-gradient(180deg, #0a0f0a 0%, #111a11 40%, #0d140d 100%);
            color:var(--text-primary);
            text-align:center;
            padding:42px 20px;
            font-family:'Inter', sans-serif;
            font-weight:500;
        }

        h2{
            font-family:'Playfair Display',serif;
            font-size:34px;
            font-weight:800;
            color:var(--gold);
        }

        .card-result{
            background: linear-gradient(135deg,rgba(30,77,43,0.2),rgba(18,30,18,0.9));
            padding:30px;
            border:1px solid rgba(201,168,76,0.15);
            border-radius:14px;
            max-width:660px;
            margin:auto;
            backdrop-filter: blur(8px);
            box-shadow:0 18px 45px rgba(0,0,0,0.3);
        }

        .result-title{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:260px;
            padding:10px 22px;
            border-radius:8px;
            font-size:28px;
            font-weight:700;
        }

        .win{ color:var(--green); background:var(--green-bg); }
        .lose{ color:var(--red); background:var(--red-bg); }
        .draw{ color:var(--gold-light); }

        .round-box{
            background: rgba(18,30,18,0.6);
            border:1px solid rgba(201,168,76,0.08);
            padding:16px;
            border-radius:8px;
            margin-bottom:12px;
            text-align:left;
        }

        .round-box.winner-round{ border-left:4px solid var(--green); }
        .round-box.loser-round{ border-left:4px solid var(--red); background:rgba(192,57,43,0.06); }
        .round-box.draw-round{ border-left:4px solid var(--gold); }

        .round-title{
            display:flex;
            justify-content:space-between;
            gap:14px;
            margin-bottom:12px;
            font-size:16px;
        }

        .round-choice{
            display:flex;
            justify-content:space-between;
            gap:10px;
            color:var(--text-muted);
            line-height:1.7;
            font-size:14px;
        }

        .round-result{
            margin-top:12px;
            text-align:right;
            font-size:15px;
        }

        .score-line{ font-size:16px; color:var(--text-muted); }

        .btn-primary{
            border:0;
            border-radius:8px;
            font-weight:700;
            background:linear-gradient(135deg,#8a6d1b,#c9a84c);
            color:#1a1a0e;
            box-shadow:0 10px 18px rgba(0,0,0,0.2);
        }
        .btn-primary:hover{ filter:brightness(1.1); color:#1a1a0e; }

        @media (max-width: 767px){
            body{ padding:26px 12px; }
            h2{ font-size:28px; }
            .card-result{ max-width:100%; padding:18px; }
            .result-title{ min-width:0; width:100%; font-size:24px; }
            .round-box{ padding:14px; }
            .round-title,.round-choice{ gap:10px; }
            .round-result{ font-size:14px; }
        }
    </style>
</head>

<body>

<h2 class="mb-4">Hasil Match</h2>

<div class="card-result">

<?php if ($winner === null): ?>
    <h3 class="draw">DRAW</h3>
<?php else: ?>
    <?php $isWinner = $winner === $user_id; ?>
    <h3 class="result-title <?= $isWinner ? 'win' : 'lose' ?>">
        <?= $isWinner ? 'Kamu Menang' : 'Kamu Kalah' ?>
    </h3>
<?php endif; ?>

<p class="score-line mb-1">
    <?= e($match['player1_name']) ?> <?= (int) $match['player1_score'] ?>
    -
    <?= (int) $match['player2_score'] ?> <?= e($match['player2_name']) ?>
</p>

<hr>

<h5 class="mb-3">Detail Round</h5>

<?php foreach ($rounds as $round): ?>
    <?php
        $roundWinner = $round['winner'] ? (int) $round['winner'] : null;
        $roundClass = 'draw-round';

        if ($roundWinner === $user_id) {
            $roundClass = 'winner-round';
        } elseif ($roundWinner !== null) {
            $roundClass = 'loser-round';
        }
    ?>

    <div class="round-box <?= $roundClass ?>">
        <div class="round-title">
            <strong>Round <?= (int) $round['round_number'] ?></strong>
            <span>
                <?php if ($roundWinner): ?>
                    <?= $roundWinner === $user_id ? 'Win' : 'Lose' ?>
                <?php else: ?>
                    Draw
                <?php endif; ?>
            </span>
        </div>

        <div class="round-choice">
            <span><?= e($match['player1_name']) ?>:</span>
            <b><?= e(choiceLabel($round['player1_choice'])) ?></b>
        </div>

        <div class="round-choice">
            <span><?= e($match['player2_name']) ?>:</span>
            <b><?= e(choiceLabel($round['player2_choice'])) ?></b>
        </div>

        <div class="round-result">
            <?php if ($roundWinner): ?>
                Winner:
                <?= $roundWinner === (int) $match['player1_id']
                    ? e($match['player1_name'])
                    : e($match['player2_name']) ?>
            <?php else: ?>
                Draw
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<a href="matchmaking.php" class="btn btn-primary mt-3">
    Kembali ke Matchmaking
</a>

</div>

<script>
window.RpsAudioConfig = {
    <?php if ($winner !== null): ?>
    effect: 'audio/<?= $winner === $user_id ? 'winner' : 'loser' ?>.mp3',
    effectOnceKey: 'match-result-<?= (int) $match_id ?>-<?= (int) $user_id ?>-<?= $winner === $user_id ? 'win' : 'lose' ?>'
    <?php endif; ?>
};
</script>
<script src="js/audio-manager.js"></script>

</body>
</html>
