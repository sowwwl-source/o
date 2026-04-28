-- Migration 003 : table echoes + notification_email sur lands

ALTER TABLE lands
    ADD COLUMN notification_email VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS echoes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sender_username VARCHAR(255) NOT NULL,
    receiver_username VARCHAR(255) NOT NULL,
    body            TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_echoes_receiver (receiver_username),
    INDEX idx_echoes_sender   (sender_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
