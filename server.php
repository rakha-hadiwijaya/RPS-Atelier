<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

class Chat implements MessageComponentInterface {

    protected $clients;
    protected $connUserMap = [];
    protected $userConnMap = [];
    protected $userStatusMap = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "Server started...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        global $pdo;

        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;

        // 🔥 INIT
        if ($data['type'] === 'init') {

            $user_id = $data['user_id'];

            // kalau user reconnect → close koneksi lama
            if (isset($this->userConnMap[$user_id])) {
                $this->userConnMap[$user_id]->close();
            }

            $this->connUserMap[$from->resourceId] = $user_id;
            $this->userConnMap[$user_id] = $from;

            $pdo->prepare("UPDATE users SET is_online=1 WHERE id=?")
                ->execute([$user_id]);

            $this->sendUserListToAll();
        }

        // 💬 CHAT
        if ($data['type'] === 'chat') {

            $userId = $this->connUserMap[$from->resourceId] ?? null;
            $message = trim($data['message'] ?? '');

            if (!$userId || $message === '') {
                return;
            }

            $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $username = $stmt->fetchColumn();

            $pdo->prepare("
                INSERT INTO messages (user_id, message)
                VALUES (?, ?)
            ")->execute([$userId, $message]);

            $payload = json_encode([
                'type' => 'chat',
                'name' => $username,
                'message' => $message
            ]);

            foreach ($this->clients as $client) {
                $client->send($payload);
            }
        }

        // 🎮 STATUS
        if ($data['type'] === 'status') {
            $this->userStatusMap[$data['user_id']] = $data['status'];
            $this->sendUserListToAll();
        }

        // 🎯 CHALLENGE
        if ($data['type'] === 'challenge') {

            $fromId = $data['from'];
            $toId = $data['to'];

            // cek status target
            $targetStatus = $this->userStatusMap[$toId] ?? 'AFK';

            if ($targetStatus !== 'READY') {

                if (isset($this->userConnMap[$fromId])) {
                    $this->userConnMap[$fromId]->send(json_encode([
                        'type' => 'error',
                        'message' => 'Player sedang AFK 😴'
                    ]));
                }
                return;
            }

            // 🔥 ambil username penantang
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?");
            $stmt->execute([$fromId]);
            $fromName = $stmt->fetchColumn();

            if (isset($this->userConnMap[$toId])) {
                $this->userConnMap[$toId]->send(json_encode([
                    'type' => 'challenge',
                    'from' => $fromId,
                    'from_name' => $fromName // 🔥 penting!
                ]));
            }
        }

        // ✅ ACCEPT
        if ($data['type'] === 'challenge_response') {

            // kalau ditolak
            if (!$data['accepted']) {

                if (isset($this->userConnMap[$data['from']])) {
                    $this->userConnMap[$data['from']]->send(json_encode([
                        'type' => 'challenge_rejected'
                    ]));
                }
                return;
            }

            // kalau diterima
            $pdo->prepare("
                INSERT INTO matches
                    (player1_id, player2_id, status, current_round_started_at, player1_last_seen_at, player2_last_seen_at)
                VALUES (?, ?, 'playing', NOW(3), NOW(3), NOW(3))
            ")->execute([$data['from'], $data['to']]);

            $match_id = $pdo->lastInsertId();

            foreach ([$data['from'], $data['to']] as $uid) {
                if (isset($this->userConnMap[$uid])) {
                    $this->userConnMap[$uid]->send(json_encode([
                        'type' => 'start_game',
                        'match_id' => $match_id,
                        'opponent_id' => $uid == $data['from'] ? $data['to'] : $data['from']
                    ]));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        global $pdo;

        if (isset($this->connUserMap[$conn->resourceId])) {

            $user_id = $this->connUserMap[$conn->resourceId];

            unset($this->connUserMap[$conn->resourceId]);
            unset($this->userConnMap[$user_id]);

            $pdo->prepare("UPDATE users SET is_online=0 WHERE id=?")
                ->execute([$user_id]);
        }

        $this->clients->detach($conn);
        $this->sendUserListToAll();
    }

    public function sendUserListToAll() {
        global $pdo;

        $users = $pdo->query("
            SELECT
                id,
                username,
                is_online,
                streak,
                points,
                total_win,
                total_lose,
                total_draw,
                total_match,
                rock_count,
                paper_count,
                scissors_count
            FROM users
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$u) {
            $u['status'] = $this->userStatusMap[$u['id']] ?? 'AFK';
        }

        $data = json_encode([
            'type' => 'userList',
            'users' => $users
        ]);

        foreach ($this->clients as $client) {
            $client->send($data);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

$server = Ratchet\Server\IoServer::factory(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(new Chat())
    ),
    8080
);

$server->run();
