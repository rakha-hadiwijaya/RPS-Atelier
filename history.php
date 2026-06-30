<?php
require 'auth_check.php';
require 'db.php';

$viewerId = (int) $_SESSION['user_id'];
$profileId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) ?: $viewerId;

$userStmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$userStmt->execute([$profileId]);
$profile = $userStmt->fetch();

if (!$profile) {
    die('User tidak ditemukan');
}

$matchesStmt = $pdo->prepare("
    SELECT
        m.*,
        p1.username AS player1_name,
        p2.username AS player2_name,
        rh1.old_points AS player1_old_points,
        rh1.new_points AS player1_new_points,
        rh2.old_points AS player2_old_points,
        rh2.new_points AS player2_new_points
    FROM matches m
    JOIN users p1 ON p1.id = m.player1_id
    JOIN users p2 ON p2.id = m.player2_id
    LEFT JOIN rating_history rh1 ON rh1.match_id = m.id AND rh1.user_id = m.player1_id
    LEFT JOIN rating_history rh2 ON rh2.match_id = m.id AND rh2.user_id = m.player2_id
    WHERE m.status = 'finished'
      AND (m.player1_id = ? OR m.player2_id = ?)
    ORDER BY m.id DESC
    LIMIT 20
");
$matchesStmt->execute([$profileId, $profileId]);
$matches = $matchesStmt->fetchAll();

$rounds = [];
if ($matches) {
    $placeholders = implode(',', array_fill(0, count($matches), '?'));
    $roundStmt = $pdo->prepare("
        SELECT *
        FROM match_rounds
        WHERE match_id IN ($placeholders)
        ORDER BY match_id DESC, round_number ASC
    ");
    $roundStmt->execute(array_column($matches, 'id'));

    foreach ($roundStmt->fetchAll() as $round) {
        $rounds[(int) $round['match_id']][] = $round;
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '-', ENT_QUOTES, 'UTF-8');
}

function nameText(?string $name): string
{
    return ucwords($name ?? '-');
}

function choiceText(?string $choice, $ms): string
{
    $label = ucwords($choice ?: '-');

    if ($ms === null) {
        return $label;
    }

    return $label . '(' . number_format(((int) $ms) / 1000, 1) . 'd)';
}

function pointsText($oldPoints, $newPoints): string
{
    if ($oldPoints === null || $newPoints === null) {
        return '-';
    }

    return (int) $oldPoints . ' &rarr; ' . (int) $newPoints;
}

$winrate = (int) $profile['total_match'] > 0
    ? round(((int) $profile['total_win'] / (int) $profile['total_match']) * 100)
    : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>History <?= e(nameText($profile['username'])) ?> - RPS Atelier</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style_lobby.css">
</head>
<body>

<nav class="navbar-top">
    <div class="container">
        <a href="chat.php" class="navbar-brand-custom">RPS ATELIER</a>
        <div class="nav-right">
            <span class="nav-user"><?= e($_SESSION['username']) ?></span>
            <a href="logout.php" class="nav-link-custom">Logout</a>
        </div>
    </div>
</nav>

<div class="container" style="padding:20px 0;">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- PROFILE HEADER -->
            <div class="history-header-card">
                <div class="history-username"><?= e(nameText($profile['username'])) ?></div>
                <div class="history-stats">
                    <span>📈 Winrate <?= $winrate ?>%</span>
                    <span>⭐ Poin <?= (int) $profile['points'] ?></span>
                    <span>🔥 Streak <?= (int) $profile['streak'] ?></span>
                    <span>🎮 Match <?= (int) $profile['total_match'] ?></span>
                </div>
            </div>

            <?php if ($profileId !== $viewerId): ?>
                <a href="matchmaking.php" class="btn-back-matchmaking" id="btn-back-matchmaking">← Kembali ke Matchmaking</a>
            <?php endif; ?>

            <!-- MATCH HISTORY -->
            <h5 style="color:var(--gold);font-family:'Playfair Display',serif;margin-bottom:16px;">Perkembangan Rating</h5>

            <?php if (!$matches): ?>
                <div style="text-align:center;color:var(--text-muted);padding:30px;">Belum ada match selesai.</div>
            <?php else: ?>
                <?php foreach ($matches as $match): ?>
                    <?php
                        $p1Name = nameText($match['player1_name']);
                        $p2Name = nameText($match['player2_name']);
                        $winnerName = null;
                        $isProfileWinner = (int) $match['winner_id'] === $profileId;
                        $cardClass = $isProfileWinner ? 'rating-card-win' : 'rating-card-lose';

                        if ((int) $match['winner_id'] === (int) $match['player1_id']) {
                            $winnerName = $p1Name;
                        } elseif ((int) $match['winner_id'] === (int) $match['player2_id']) {
                            $winnerName = $p2Name;
                        }
                    ?>

                    <div class="rating-card <?= $cardClass ?>">
                        <div class="rating-header">
                            <div>
                                <strong>Match #<?= (int) $match['id'] ?></strong>
                                <div class="rating-winner">
                                    <?= $winnerName ? e($winnerName) . ' Win' : 'Draw' ?>
                                    <?php if (($match['finish_reason'] ?? 'normal') !== 'normal'): ?>
                                        <span class="self-badge"><?= e($match['finish_reason']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rating-score">
                                Score <?= (int) $match['player1_score'] ?>-<?= (int) $match['player2_score'] ?>
                            </div>
                        </div>

                        <div class="rating-points">
                            <?= e($p1Name) ?> <?= pointsText($match['player1_old_points'], $match['player1_new_points']) ?>
                            <span>|</span>
                            <?= e($p2Name) ?> <?= pointsText($match['player2_old_points'], $match['player2_new_points']) ?>
                        </div>

                        <div class="rating-rounds">
                            <?php foreach (($rounds[(int) $match['id']] ?? []) as $round): ?>
                                <?php
                                    $roundWinner = null;
                                    $choiceLeft = choiceText($round['player1_choice'], $round['player1_response_ms']);
                                    $choiceRight = choiceText($round['player2_choice'], $round['player2_response_ms']);
                                    $choiceSeparator = '=';

                                    if ((int) $round['winner'] === (int) $match['player1_id']) {
                                        $roundWinner = $p1Name;
                                        $choiceSeparator = '>';
                                    } elseif ((int) $round['winner'] === (int) $match['player2_id']) {
                                        $roundWinner = $p2Name;
                                        $choiceLeft = choiceText($round['player2_choice'], $round['player2_response_ms']);
                                        $choiceRight = choiceText($round['player1_choice'], $round['player1_response_ms']);
                                        $choiceSeparator = '>';
                                    }
                                ?>
                                <div>
                                    Round <?= (int) $round['round_number'] ?>
                                    |
                                    <?= $roundWinner ? e($roundWinner) . ' Win' : 'Draw' ?>
                                    |
                                    <?= e($choiceLeft) ?>
                                    <?= $choiceSeparator ?>
                                    <?= e($choiceRight) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- BOTTOM NAV -->
<div class="bottom-nav">
    <a href="leaderboard.php">
        <span class="nav-icon">🏆</span>
        <span>Leaderboard</span>
    </a>
    <a href="chat.php" class="home-btn">
        <span class="nav-icon">🏠</span>
        <span>Home</span>
    </a>
    <a href="history.php" class="active">
        <span class="nav-icon">📊</span>
        <span>History</span>
    </a>
</div>

<script>
window.RpsAudioConfig = {
    track: 'audio/all-page.mp3',
    trackKey: 'all-page'
};
</script>
<script src="js/audio-manager.js"></script>
</body>
</html>
