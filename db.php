<?php
$pdo = new PDO("mysql:host=localhost;dbname=uniska_chatapp", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

try {
    $userColumns = [
        'total_win' => 'INT DEFAULT 0',
        'total_lose' => 'INT DEFAULT 0',
        'total_draw' => 'INT DEFAULT 0',
        'total_match' => 'INT DEFAULT 0',
        'rock_count' => 'INT DEFAULT 0',
        'paper_count' => 'INT DEFAULT 0',
        'scissors_count' => 'INT DEFAULT 0',
    ];

    foreach ($userColumns as $column => $definition) {
        ensureColumn($pdo, 'users', $column, $definition);
    }

    $matchColumns = [
        'player1_score' => 'INT DEFAULT 0',
        'player2_score' => 'INT DEFAULT 0',
        'processed' => 'TINYINT(1) DEFAULT 0',
        'current_round_started_at' => 'DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3)',
        'player1_response_ms' => 'INT DEFAULT NULL',
        'player2_response_ms' => 'INT DEFAULT NULL',
        'player1_last_seen_at' => 'DATETIME(3) DEFAULT NULL',
        'player2_last_seen_at' => 'DATETIME(3) DEFAULT NULL',
        'finish_reason' => "VARCHAR(30) DEFAULT 'normal'",
    ];

    foreach ($matchColumns as $column => $definition) {
        ensureColumn($pdo, 'matches', $column, $definition);
    }

    $roundColumns = [
        'player1_response_ms' => 'INT DEFAULT NULL',
        'player2_response_ms' => 'INT DEFAULT NULL',
        'result_reason' => "VARCHAR(30) DEFAULT 'normal'",
    ];

    foreach ($roundColumns as $column => $definition) {
        ensureColumn($pdo, 'match_rounds', $column, $definition);
    }
} catch (Throwable $e) {
    // Biarkan error query asli terlihat di halaman yang membutuhkan kolom tersebut.
}
?>
