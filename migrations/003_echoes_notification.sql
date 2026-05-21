-- Migration 003 : table echoes + notification_email sur lands

SET @echoes_notification_email_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'lands'
      AND COLUMN_NAME = 'notification_email'
);

SET @echoes_notification_email_sql := IF(
    @echoes_notification_email_exists = 0,
    'ALTER TABLE lands ADD COLUMN notification_email VARCHAR(255) DEFAULT NULL',
    'SELECT 1'
);

PREPARE echoes_notification_email_stmt FROM @echoes_notification_email_sql;
EXECUTE echoes_notification_email_stmt;
DEALLOCATE PREPARE echoes_notification_email_stmt;

CREATE TABLE IF NOT EXISTS echoes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sender_username VARCHAR(255) NOT NULL,
    receiver_username VARCHAR(255) NOT NULL,
    body            TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_echoes_receiver (receiver_username),
    INDEX idx_echoes_sender   (sender_username),
    INDEX idx_echoes_receiver_unread (receiver_username, is_read, created_at),
    INDEX idx_echoes_thread (sender_username, receiver_username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
