<?php require 'auth_check.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>RPS Game</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style_game.css">
</head>

<body>

<div class="game-container">

    <!-- 🔥 OPPONENT -->
    <div class="opponent-area text-center">
        <div class="avatar">👤</div>
        <div class="label">OPPONENT</div>
        <div id="enemy-cards" class="enemy-cards"></div>
    </div>

    <!-- 🔥 ARENA -->
    <div class="arena text-center">
        <h4 id="status">Menunggu pilihan...</h4>
        <div id="countdown" class="countdown" style="display:none;"></div>
        <h2 id="result"></h2>
    </div>

    <div id="backBtn" class="mt-3" style="display:none;">
    <button class="btn btn-light" onclick="backToLobby()">
        Kembali ke Lobby
    </button>
    </div>

    <!-- 🔥 PLAYER CARDS -->
    <div id="cards" class="card-container"></div>

</div>

<script>
const userId = <?= $_SESSION['user_id'] ?>;
const match_id = sessionStorage.getItem('match_id');

const cardIcons = {
    batu: "🪨",
    kertas: "📄",
    gunting: "✂️"
};

let myChoice = null;
let isRevealed = false;
let roundFinished = false;
let lastRoundSeen = 0;
let matchFinished = false;

// 🔥 RANDOM CARD
let cards = [
    { name: 'batu', icon: '🪨' },
    { name: 'kertas', icon: '📄' },
    { name: 'gunting', icon: '✂️' }
];

cards.sort(() => Math.random() - 0.5);

// 🎴 PLAYER CARD
function renderCards() {
    const container = document.getElementById('cards');

    container.innerHTML = cards.map((c, i) => `
        <div class="card-rps" 
            style="transform: rotate(${(i-1)*8}deg)"
            onclick="pilih('${c.name}')">
            <div class="icon">${c.icon}</div>
            <div class="label">${c.name.toUpperCase()}</div>
        </div>
    `).join('');
}

// 🎴 ENEMY CARD (HIDDEN - 3 cards default)
function renderEnemyCards() {
    const container = document.getElementById('enemy-cards');
    container.classList.remove('enemy-chosen');

    container.innerHTML = [0,1,2].map((_, i) => `
        <div class="card-back"
            style="transform: rotate(${(i-1)*8}deg)">
        </div>
    `).join('');
}

// 🎴 ENEMY CARD (CHOSEN STATE - 1 card, enlarged, darkened)
function renderEnemyChosen() {
    const container = document.getElementById('enemy-cards');
    container.classList.add('enemy-chosen');

    container.innerHTML = `
        <div class="card-back card-back-chosen">
        </div>
    `;
}

renderCards();
renderEnemyCards();

// 🎯 PILIH
function pilih(choice) {

    if (myChoice !== null) return; // 🔥 ini penting

    myChoice = choice;

    document.getElementById('status').innerText =
        "Kamu memilih: " + choice;

    fetch('play.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `match_id=${match_id}&user_id=${userId}&choice=${choice}`
    });
}

// 🔁 SYNC
async function loadState() {
    await sendGameAction('heartbeat');

    fetch('get_match.php?match_id=' + match_id)
    .then(res => res.json())
    .then(data => {

        if (!data) return;

        const isP1 = userId == data.player1_id;

        if (data.status === 'finished') {
            matchFinished = true;

            if (data.finish_reason && data.finish_reason !== 'normal') {
                const message = data.winner_id == userId
                    ? `Lawan ${data.finish_reason === 'timeout' ? 'kehabisan waktu' : 'disconnect'}, kamu menang.`
                    : `Kamu kalah karena ${data.finish_reason}.`;

                document.getElementById('status').innerText = message;
                document.getElementById('result').innerText = '';
                document.getElementById('countdown').style.display = 'none';
                renderEnemyCards();
            }

            setTimeout(() => {
                window.location.href = "result.php?match_id=" + match_id;
            }, data.finish_reason && data.finish_reason !== 'normal' ? 700 : 1500);

            return;
        }

        // =========================
        // 🧠 ROUND BARU DETECT
        // =========================
        if (data.last_round && (!data.last_round.result_reason || data.last_round.result_reason === 'normal')) {

            const currentRound = parseInt(data.last_round.round_number);

            // kalau round baru
            if (currentRound !== lastRoundSeen) {

                lastRoundSeen = currentRound;

                showResult(
                    data.last_round.player1_choice,
                    data.last_round.player2_choice,
                    isP1
                );

                // reset buat next round
                setTimeout(() => {

                    myChoice = null;
                    isRevealed = false;

                    renderCards();
                    renderEnemyCards();

                    document.getElementById('result').innerText = "";
                    document.getElementById('status').innerText = "Pilih kartu...";

                }, 2000);
            }
        }

        // =========================
        // ⏳ WAITING — perbaikan logika
        // =========================
        const enemyField = isP1 ? data.p2 : data.p1;

        if (!myChoice) {
            if (enemyField) {
                // Lawan sudah memilih, kita belum
                document.getElementById('status').innerText = "Lawan sudah memilih! Pilih kartu...";
                renderEnemyChosen();
            } else {
                document.getElementById('status').innerText = "Pilih kartu...";
            }
        } else if (!enemyField) {
            document.getElementById('status').innerText = "Menunggu lawan...";
        }

        // Jika lawan sudah memilih tapi belum reveal, tampilkan kartu chosen
        if (enemyField && !isRevealed) {
            renderEnemyChosen();
        }

        renderCountdown(data);

        // =========================
        // 🏆 FINISHED
        // =========================
    });
}

function renderCountdown(data) {
    const countdown = document.getElementById('countdown');

    if (data.status === 'finished' || data.timeout_remaining === null) {
        countdown.style.display = 'none';
        countdown.innerText = '';
        return;
    }

    const remaining = Math.ceil(Number(data.timeout_remaining));
    countdown.style.display = 'inline-flex';
    countdown.innerText = `Sisa waktu lawan: ${remaining}s`;
}

function sendGameAction(action) {
    if (!match_id) return Promise.resolve();

    return fetch('game_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `match_id=${encodeURIComponent(match_id)}&action=${encodeURIComponent(action)}`,
        keepalive: true
    }).catch(() => {});
}

// 🧠 RESULT PER ROUND
function showResult(p1, p2, isP1) {

    if (isRevealed) return;

    isRevealed = true;
    roundFinished = true;

    let enemyChoice = isP1 ? p2 : p1;

    revealEnemy(enemyChoice);

    let p1Win =
        (p1 === 'batu' && p2 === 'gunting') ||
        (p1 === 'gunting' && p2 === 'kertas') ||
        (p1 === 'kertas' && p2 === 'batu');

    let result;

    if (p1 === p2) {
        result = "Draw 😐";
    } else if (isP1) {
        result = p1Win ? "Kamu menang 😎" : "Kamu kalah 😢";
    } else {
        result = p1Win ? "Kamu kalah 😢" : "Kamu menang 😎";
    }

    document.getElementById('result').innerText = result;

    // 🔥 delay sebelum next round
    setTimeout(nextRound, 2000);
}

// 🔄 RESET ROUND
function nextRound() {

    myChoice = null;
    isRevealed = false;
    roundFinished = false;

    document.getElementById('result').innerText = "";
    document.getElementById('status').innerText = "Pilih kartu...";

    renderEnemyCards();
    renderCards();
}

// 🔥 REVEAL MUSUH
function revealEnemy(choice) {
    const container = document.getElementById('enemy-cards');
    container.classList.remove('enemy-chosen');

    if (!cardIcons[choice]) {
        renderEnemyCards();
        return;
    }

    container.innerHTML = `
        <div class="card-rps reveal">
            <div class="icon">${cardIcons[choice]}</div>
            <div class="label">${choice.toUpperCase()}</div>
        </div>
    `;
}

// 🔁 AUTO SYNC
let interval = setInterval(loadState, 1000);

// ❌ CLEANUP
window.addEventListener("beforeunload", function () {
    clearInterval(interval);

    if (!matchFinished && match_id) {
        sendForfeitBeacon();
    }
});

window.addEventListener("pagehide", function () {
    if (!matchFinished && match_id) {
        sendForfeitBeacon();
    }
});

function sendForfeitBeacon() {
    const data = new URLSearchParams();
    data.append('match_id', match_id);
    data.append('action', 'forfeit');
    navigator.sendBeacon('game_action.php', data);
}
</script>

<script>
window.RpsAudioConfig = {
    track: 'audio/ingame.mp3',
    trackKey: 'ingame'
};
</script>
<script src="js/audio-manager.js"></script>

</body>
</html>
