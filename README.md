# RPS Atelier - Realtime Chat & Rock Paper Scissors

Aplikasi web multiplayer real-time yang menggabungkan fitur global chat dan permainan Batu Gunting Kertas (Rock Paper Scissors) secara interaktif. Dibangun menggunakan PHP, WebSocket (Ratchet), dan MySQL.

## Fitur Utama
- **Real-time Global Chat**: Ngobrol dengan pemain lain secara real-time.
- **Sistem Matchmaking**: Lihat pemain online, profil mereka, persentase kemenangan (winrate), poin, dan streak.
- **Tantang Pemain (Challenge)**: Ajak pemain online bertanding secara real-time.
- **Gameplay Interaktif**: Animasi kartu, timer/countdown, dan sistem ronde permainan Batu Gunting Kertas.
- **Sistem Poin & Leaderboard**: Papan peringkat berdasarkan poin dan kemenangan.
- **History Pertandingan**: Lihat riwayat permainan kamu dan lawan.

## Persyaratan Sistem
- PHP 7.4 atau lebih baru (direkomendasikan PHP 8.x)
- Composer
- MySQL / MariaDB (XAMPP / Laragon)

## Cara Instalasi & Menjalankan

### 1. Persiapan Database
1. Buka database manager kamu (misal: phpMyAdmin, HeidiSQL, atau terminal MySQL).
2. Buat database baru bernama `uniska_chatapp`.
   ```sql
   CREATE DATABASE uniska_chatapp;
   USE uniska_chatapp;
   ```
3. Eksekusi query berikut untuk membuat struktur tabel awal:
   ```sql
   CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       username VARCHAR(50) NOT NULL,
       password VARCHAR(255) NOT NULL,
       is_online TINYINT(1) DEFAULT 0,
       created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
       streak INT DEFAULT 0,
       points INT DEFAULT 0,
       total_win INT DEFAULT 0,
       total_lose INT DEFAULT 0,
       total_draw INT DEFAULT 0,
       total_match INT DEFAULT 0,
       rock_count INT DEFAULT 0,
       paper_count INT DEFAULT 0,
       scissors_count INT DEFAULT 0
   );

   CREATE TABLE messages (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       message TEXT,
       created_at DATETIME DEFAULT CURRENT_TIMESTAMP
   );

   CREATE TABLE matches (
       id INT AUTO_INCREMENT PRIMARY KEY,
       player1_id INT,
       player2_id INT,
       player1_choice VARCHAR(10),
       player2_choice VARCHAR(10),
       winner_id INT,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       status VARCHAR(20) DEFAULT 'waiting',
       player1_score INT DEFAULT 0,
       player2_score INT DEFAULT 0,
       processed TINYINT(1) DEFAULT 0,
       current_round_started_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3),
       player1_response_ms INT DEFAULT NULL,
       player2_response_ms INT DEFAULT NULL,
       player1_last_seen_at DATETIME(3) DEFAULT NULL,
       player2_last_seen_at DATETIME(3) DEFAULT NULL,
       finish_reason VARCHAR(30) DEFAULT 'normal'
   );

   CREATE TABLE match_rounds (
       id INT AUTO_INCREMENT PRIMARY KEY,
       match_id INT,
       round_number INT,
       player1_choice VARCHAR(20),
       player2_choice VARCHAR(20),
       winner INT,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       player1_response_ms INT DEFAULT NULL,
       player2_response_ms INT DEFAULT NULL,
       result_reason VARCHAR(30) DEFAULT 'normal'
   );
   ```
   *Catatan: Aplikasi memiliki fungsi auto-migrate untuk beberapa kolom di tabel `users`, `matches`, dan `match_rounds` saat file `db.php` dijalankan.*

### 2. Instalasi Dependensi
Buka terminal/command prompt, arahkan ke folder proyek (contoh: `C:/laragon/www/chat-ws`), lalu jalankan perintah:
```bash
composer install
```
*(Pastikan composer sudah terinstall, kamu bisa mengeceknya dengan perintah `composer -v`)*

### 3. Menjalankan Server WebSocket
Agar fitur real-time (chat & gameplay) berfungsi, kamu harus menjalankan WebSocket server. Di terminal, pastikan kamu berada di direktori proyek, lalu jalankan:
```bash
php server.php
```
Biarkan terminal ini tetap terbuka selama aplikasi digunakan.

### 4. Mengakses Aplikasi
Buka web browser dan akses URL:
```
http://localhost/chat-ws/login.php
```
*(Sesuaikan URL dengan nama folder proyek kamu)*

## Mengakses dari Perangkat Lain (Satu Jaringan WiFi)
Aplikasi ini mendukung untuk dimainkan secara multiplayer beda device. Syaratnya kedua perangkat harus terhubung ke jaringan WiFi/LAN yang sama.
1. Cari IP Address laptop/komputer server (buka CMD dan ketik `ipconfig`, cari bagian IPv4 Address).
2. Pastikan web server (Apache/Nginx) dan WebSocket server (`php server.php`) dalam keadaan jalan.
3. Dari perangkat lain (misal HP), buka browser dan masukkan alamat: `http://<IP-SERVER>/chat-ws/login.php` (contoh: `http://192.168.1.10/chat-ws/login.php`).
4. **Catatan**: Jika tidak bisa diakses, pastikan kamu sudah mengizinkan port `80` (HTTP) dan port `8080` (WebSocket) di Windows Firewall.
