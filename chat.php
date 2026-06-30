<?php
require 'auth_check.php';
require 'db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>RPS Atelier - Lobby</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="RPS Atelier - Rock Paper Scissors Ranked Game Lobby">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style_lobby.css">
</head>
<body>

<!-- NAVBAR TOP -->
<nav class="navbar-top">
    <div class="container">
        <a href="chat.php" class="navbar-brand-custom">RPS ATELIER</a>
        <div class="nav-right">
            <span class="nav-user"><?= e($_SESSION['username']) ?></span>
            <a href="logout.php" class="nav-link-custom">Logout</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<div class="lobby-hero">
    <h1 class="hero-title">ROCK, PAPER,<br>SCISSORS: RANKED</h1>
    <p class="hero-subtitle">Prove your dominance</p>
    <a href="matchmaking.php" class="btn-matchmaking" id="btn-matchmaking">MATCHMAKING ⚔️</a>
</div>

<!-- BOTTOM NAV -->
<div class="bottom-nav">
    <a href="leaderboard.php">
        <span class="nav-icon">🏆</span>
        <span>Leaderboard</span>
    </a>
    <a href="chat.php" class="home-btn active">
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
