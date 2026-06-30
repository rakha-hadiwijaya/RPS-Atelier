<?php
require 'auth_check.php';
require 'db.php';

$messagesQuery = $pdo->query("
    SELECT m.message, m.created_at, u.username
    FROM messages m
    JOIN users u ON u.id = m.user_id
    ORDER BY m.created_at ASC, m.id ASC
    LIMIT 100
");
$messages = $messagesQuery->fetchAll();

$userId = (int) $_SESSION['user_id'];

$selfStmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$selfStmt->execute([$userId]);
$self = $selfStmt->fetch();

$winrate = (int)$self['total_match'] > 0
    ? round(((int)$self['total_win'] / (int)$self['total_match']) * 100) : 0;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Matchmaking - RPS Atelier</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style_lobby.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-top">
    <div class="container">
        <a href="chat.php" class="navbar-brand-custom">RPS ATELIER</a>
        <div class="nav-right">
            <span class="nav-user"><?= e($_SESSION['username']) ?></span>
            <a href="logout.php" class="nav-link-custom">Logout</a>
        </div>
    </div>
</nav>

<div class="container mm-container">
    <div class="mm-grid">
        <!-- SELF CARD -->
        <div class="mm-self-card">
            <div class="mm-avatar">👤</div>
            <div class="mm-username"><?= e($_SESSION['username']) ?></div>
            <div id="self-status-badge" class="mm-status-badge ready">READY</div>
            <div class="mm-stats-grid">
                <div class="mm-stat-item">
                    <div class="mm-stat-label">Streak</div>
                    <div class="mm-stat-value">🔥 <?= (int)$self['streak'] ?></div>
                </div>
                <div class="mm-stat-item">
                    <div class="mm-stat-label">Points</div>
                    <div class="mm-stat-value">⭐ <?= (int)$self['points'] ?></div>
                </div>
                <div class="mm-stat-item">
                    <div class="mm-stat-label">Winrate</div>
                    <div class="mm-stat-value">📈 <?= $winrate ?>%</div>
                </div>
                <div class="mm-stat-item">
                    <div class="mm-stat-label">Matches</div>
                    <div class="mm-stat-value">🎮 <?= (int)$self['total_match'] ?></div>
                </div>
            </div>
        </div>

        <!-- ONLINE PLAYERS -->
        <div class="mm-players-card">
            <div class="mm-players-header">
                <span class="mm-players-title">Online Players</span>
                <span class="mm-online-count">
                    <span class="mm-online-dot"></span>
                    <span id="online-count">0</span> online
                </span>
            </div>
            <div id="user-list" class="mm-players-list"></div>
        </div>
    </div>

    <!-- CHAT -->
    <div class="mm-chat-section">
        <div class="mm-chat-header">💬 Global Chat</div>
        <div id="chat">
            <?php foreach ($messages as $message): ?>
                <p><b><?= e($message['username']) ?>:</b> <?= e($message['message']) ?></p>
            <?php endforeach; ?>
        </div>
        <div class="chat-input-group">
            <input id="message" placeholder="Ketik pesan..." autocomplete="off">
            <button onclick="sendMessage()">Kirim</button>
        </div>
    </div>
</div>

<!-- POPUP -->
<div id="challengePopup" class="challenge-popup" style="display:none;">
    <div class="popup-box">
        <h4 id="challengeTitle">Tantangan Match</h4>
        <p id="challengeText">Ada yang menantang kamu!</p>
        <div class="popup-actions">
            <button id="challengeAcceptBtn" class="btn popup-primary" onclick="acceptChallenge()">Terima</button>
            <button id="challengeRejectBtn" class="btn popup-danger" onclick="rejectChallenge()">Tolak</button>
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
    <a href="history.php">
        <span class="nav-icon">📊</span>
        <span>History</span>
    </a>
</div>

<script>
const userId = <?= $_SESSION['user_id'] ?>;
const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
const conn = new WebSocket(`${wsProtocol}//${window.location.hostname}:8080`);

let currentChallenger = null;
let pendingChallengeTarget = null;
let pendingProfileTarget = null;
let challengeMode = null;
let myStatus = 'READY';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function scrollChatToBottom(behavior = 'auto') {
    const chat = document.getElementById('chat');
    chat.scrollTo({ top: chat.scrollHeight, behavior: behavior });
}

window.addEventListener('load', () => scrollChatToBottom());

// CONNECT - auto set READY
conn.onopen = function() {
    conn.send(JSON.stringify({ type: 'init', user_id: userId }));
    // Auto READY when entering matchmaking
    conn.send(JSON.stringify({ type: 'status', user_id: userId, status: 'READY' }));
};

// RECEIVE
conn.onmessage = function(e) {
    const data = JSON.parse(e.data);

    if (data.type === 'chat') {
        document.getElementById('chat').innerHTML +=
            `<p><b>${escapeHtml(data.name)}:</b> ${escapeHtml(data.message)}</p>`;
        scrollChatToBottom('smooth');
    }

    if (data.type === 'userList') {
        const me = data.users.find(u => Number(u.id) === Number(userId));
        myStatus = me?.status || 'READY';
        renderUsers(data.users);
    }

    if (data.type === 'challenge') {
        currentChallenger = data.from;
        pendingChallengeTarget = null;
        challengeMode = 'incoming';
        document.getElementById('challengeTitle').innerText = 'Tantangan Masuk';
        document.getElementById('challengeText').innerText = `${data.from_name} menantang kamu!`;
        document.getElementById('challengeAcceptBtn').innerText = 'Terima';
        document.getElementById('challengeRejectBtn').innerText = 'Tolak';
        document.getElementById('challengeRejectBtn').style.display = '';
        document.getElementById('challengePopup').style.display = 'flex';
    }

    if (data.type === 'start_game') {
        sessionStorage.setItem('match_id', data.match_id);
        setTimeout(() => {
            window.location.href = "game.php?opponent=" + data.opponent_id;
        }, 200);
    }

    if (data.type === 'challenge_rejected') {
        showNotice('Tantangan Ditolak', 'Player menolak tantangan kamu.');
    }

    if (data.type === 'error') {
        showNotice('Tidak Bisa Menantang', data.message);
    }
};

// SEND CHAT + Enter key fix
function sendMessage() {
    const input = document.getElementById('message');
    const msg = input.value;
    if (!msg.trim()) return;
    conn.send(JSON.stringify({ type: 'chat', user_id: userId, message: msg }));
    input.value = '';
}

document.getElementById('message').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
});

// STATUS
function setStatus(status) {
    conn.send(JSON.stringify({ type: 'status', user_id: userId, status: status }));
}

// Choice tendency logic
function getChoiceTendency(u) {
    const rock = parseInt(u.rock_count) || 0;
    const paper = parseInt(u.paper_count) || 0;
    const scissors = parseInt(u.scissors_count) || 0;
    const total = rock + paper + scissors;
    if (total < 5) return null;

    const rockPct = (rock / total) * 100;
    const paperPct = (paper / total) * 100;
    const scissorsPct = (scissors / total) * 100;

    if (rockPct > 50 || paperPct > 50 || scissorsPct > 50) {
        return { repeater: true, label: '⚠️ Cenderung mengulang pilihan' };
    }
    return { repeater: false, label: '✅ Tidak cenderung mengulang' };
}

// RENDER USERS
function renderUsers(users) {
    const list = document.getElementById('user-list');
    const onlineUsers = users.filter(u => u.is_online == 1 && Number(u.id) !== Number(userId));

    document.getElementById('online-count').textContent = onlineUsers.length + 1;

    // Update self status badge
    const me = users.find(u => Number(u.id) === Number(userId));
    if (me) {
        const badge = document.getElementById('self-status-badge');
        badge.textContent = me.status || 'READY';
        badge.className = 'mm-status-badge ' + ((me.status === 'READY') ? 'ready' : 'afk');
    }

    list.innerHTML = onlineUsers.map(u => {
        let winrate = 0;
        if (u.total_match > 0) winrate = Math.round((u.total_win / u.total_match) * 100);

        const safeUsername = escapeHtml(u.username);
        const challengeName = String(u.username ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        const tendency = getChoiceTendency(u);

        let tendencyHtml = '';
        if (tendency) {
            tendencyHtml = `<span class="mm-player-tendency ${tendency.repeater ? 'repeater' : 'varied'}">${tendency.label}</span>`;
        }

        return `
            <div class="mm-player-item" onclick="openProfile(${u.id}, '${challengeName}', '${u.status || 'AFK'}', false)">
                <div class="mm-player-info">
                    <div class="mm-player-name">${safeUsername}</div>
                    <div class="mm-player-stats">
                        <span>🔥 ${u.streak ?? 0}</span>
                        <span>⭐ ${u.points ?? 0}</span>
                        <span>📈 ${winrate}%</span>
                    </div>
                    ${tendencyHtml}
                </div>
                <span class="mm-player-status ${u.status === 'READY' ? 'ready' : 'afk'}">
                    ${u.status || 'AFK'}
                </span>
            </div>
        `;
    }).join('');

    if (onlineUsers.length === 0) {
        list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px;font-size:13px;">Belum ada player online lainnya</div>';
    }
}

// PROFILE / CHALLENGE
function openProfile(id, name, status, isSelf) {
    pendingProfileTarget = { id, name, status, isSelf };
    pendingChallengeTarget = null;
    currentChallenger = null;
    challengeMode = 'profile';

    document.getElementById('challengeTitle').innerText = isSelf ? 'Profil Kamu' : `Profil ${name}`;
    document.getElementById('challengeText').innerText = isSelf
        ? 'Lihat history pertandingan kamu.'
        : 'Pilih aksi untuk player ini.';
    document.getElementById('challengeAcceptBtn').innerText = 'Cek History';
    document.getElementById('challengeRejectBtn').innerText = isSelf ? 'Tutup' : 'Tantang';
    document.getElementById('challengeRejectBtn').style.display = '';
    document.getElementById('challengePopup').style.display = 'flex';
}

function challengeUser(id, name) {
    pendingChallengeTarget = { id, name };
    currentChallenger = null;
    challengeMode = 'outgoing';

    document.getElementById('challengeTitle').innerText = 'Ajak Bertanding';
    document.getElementById('challengeText').innerText = `Tantang ${name} untuk bermain?`;
    document.getElementById('challengeAcceptBtn').innerText = 'Tantang';
    document.getElementById('challengeRejectBtn').innerText = 'Batal';
    document.getElementById('challengeRejectBtn').style.display = '';
    document.getElementById('challengePopup').style.display = 'flex';
}

function sendChallenge(id) {
    conn.send(JSON.stringify({ type: "challenge", from: userId, to: id }));
}

function showNotice(title, message) {
    pendingChallengeTarget = null;
    currentChallenger = null;
    challengeMode = 'notice';
    document.getElementById('challengeTitle').innerText = title;
    document.getElementById('challengeText').innerText = message;
    document.getElementById('challengeAcceptBtn').innerText = 'Oke';
    document.getElementById('challengeRejectBtn').style.display = 'none';
    document.getElementById('challengePopup').style.display = 'flex';
}

function acceptChallenge() {
    if (challengeMode === 'profile' && pendingProfileTarget) {
        window.location.href = `history.php?user_id=${pendingProfileTarget.id}`;
        return;
    }
    if (challengeMode === 'outgoing' && pendingChallengeTarget) {
        sendChallenge(pendingChallengeTarget.id);
        hideChallengePopup();
        return;
    }
    if (challengeMode === 'notice') {
        hideChallengePopup();
        return;
    }
    conn.send(JSON.stringify({
        type: "challenge_response",
        from: currentChallenger,
        to: userId,
        accepted: true
    }));
    hideChallengePopup();
}

function rejectChallenge() {
    if (challengeMode === 'profile' && pendingProfileTarget) {
        if (!pendingProfileTarget.isSelf) {
            challengeUser(pendingProfileTarget.id, pendingProfileTarget.name);
            return;
        }
        hideChallengePopup();
        return;
    }
    if (challengeMode === 'incoming' && currentChallenger) {
        conn.send(JSON.stringify({
            type: "challenge_response",
            from: currentChallenger,
            to: userId,
            accepted: false
        }));
    }
    hideChallengePopup();
}

function hideChallengePopup() {
    document.getElementById("challengePopup").style.display = "none";
    document.getElementById('challengeRejectBtn').style.display = '';
    currentChallenger = null;
    pendingChallengeTarget = null;
    pendingProfileTarget = null;
    challengeMode = null;
}

// Set AFK when leaving matchmaking
window.addEventListener('beforeunload', function() {
    conn.send(JSON.stringify({ type: 'status', user_id: userId, status: 'AFK' }));
});

// Home button sets AFK
document.querySelectorAll('.home-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        conn.send(JSON.stringify({ type: 'status', user_id: userId, status: 'AFK' }));
        setTimeout(() => { window.location.href = 'chat.php'; }, 100);
    });
});
</script>

<script>
window.RpsAudioConfig = {
    track: 'audio/all-page.mp3',
    trackKey: 'all-page'
};
</script>
<script src="js/audio-manager.js"></script>

</body>
</html>
