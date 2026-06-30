CREATE DATABASE IF NOT EXISTS uniska_chatapp;
USE uniska_chatapp;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `is_online` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `streak` INT DEFAULT 0,
    `points` INT DEFAULT 0,
    `total_win` INT DEFAULT 0,
    `total_lose` INT DEFAULT 0,
    `total_draw` INT DEFAULT 0,
    `total_match` INT DEFAULT 0,
    `rock_count` INT DEFAULT 0,
    `paper_count` INT DEFAULT 0,
    `scissors_count` INT DEFAULT 0
);

CREATE TABLE `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `message` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `matches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `player1_id` INT,
    `player2_id` INT,
    `player1_choice` VARCHAR(10),
    `player2_choice` VARCHAR(10),
    `winner_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(20) DEFAULT 'waiting',
    `player1_score` INT DEFAULT 0,
    `player2_score` INT DEFAULT 0,
    `processed` TINYINT(1) DEFAULT 0,
    `current_round_started_at` DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3),
    `player1_response_ms` INT DEFAULT NULL,
    `player2_response_ms` INT DEFAULT NULL,
    `player1_last_seen_at` DATETIME(3) DEFAULT NULL,
    `player2_last_seen_at` DATETIME(3) DEFAULT NULL,
    `finish_reason` VARCHAR(30) DEFAULT 'normal'
);

CREATE TABLE `match_rounds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `match_id` INT,
    `round_number` INT,
    `player1_choice` VARCHAR(20),
    `player2_choice` VARCHAR(20),
    `winner` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `player1_response_ms` INT DEFAULT NULL,
    `player2_response_ms` INT DEFAULT NULL,
    `result_reason` VARCHAR(30) DEFAULT 'normal'
);
