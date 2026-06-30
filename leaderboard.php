<?php
require 'auth_check.php';
require 'db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$users = $pdo->query("
    SELECT id, username, points, streak, total_win, total_lose, total_draw, total_match
    FROM users
    ORDER BY
        CASE WHEN total_match > 0 THEN (total_win / total_match) ELSE 0 END DESC,
        points DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Leaderboard - RPS Atelier</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="RPS Atelier Leaderboard - Top Players Ranking">
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

<div class="container lb-container">
    <h1 class="lb-title">🏆 Leaderboard</h1>

    <div class="lb-card">
        <?php if (!$users): ?>
            <div style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada data player.</div>
        <?php else: ?>
            <?php foreach ($users as $index => $u): ?>
                <?php
                    $winrate = (int)$u['total_match'] > 0
                        ? round(((int)$u['total_win'] / (int)$u['total_match']) * 100)
                        : 0;
                    $rankClass = '';
                    if ($index === 0) $rankClass = 'gold';
                    elseif ($index === 1) $rankClass = 'silver';
                    elseif ($index === 2) $rankClass = 'bronze';
                ?>
                <div class="lb-item">
                    <div class="lb-rank <?= $rankClass ?>">#<?= $index + 1 ?></div>
                    <div>
                        <div class="lb-name"><?= e($u['username']) ?></div>
                        <div class="lb-name-sub">
                            🔥 <?= (int)$u['streak'] ?> streak &nbsp;|&nbsp;
                            ⭐ <?= (int)$u['points'] ?> pts &nbsp;|&nbsp;
                            🎮 <?= (int)$u['total_match'] ?> match
                        </div>
                    </div>
                    <div class="lb-winrate">
                        <?= $winrate ?>%
                        <small>winrate</small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- BOTTOM NAV -->
<div class="bottom-nav">
    <a href="leaderboard.php" class="active">
        <span class="nav-icon">🏆</span>
        <span>Leaderboard</span>
    </a>
    <a href="chat.php" class="home-btn">
        <span class="nav-icon">🏠</span>
        <span>Home</span>
    </a>
    <a href="history.php">
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
