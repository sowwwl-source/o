SET @signal_notification_email_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'lands'
      AND COLUMN_NAME = 'notification_email'
);

SET @signal_notification_email_sql := IF(
    @signal_notification_email_exists = 0,
    'ALTER TABLE lands ADD COLUMN notification_email VARCHAR(255) DEFAULT NULL',
    'SELECT 1'
);

PREPARE signal_notification_email_stmt FROM @signal_notification_email_sql;
EXECUTE signal_notification_email_stmt;
DEALLOCATE PREPARE signal_notification_email_stmt;

CREATE TABLE IF NOT EXISTS signal_mailboxes (
    land_slug VARCHAR(64) NOT NULL PRIMARY KEY,
    land_username VARCHAR(255) NOT NULL,
    virtual_email VARCHAR(255) NOT NULL UNIQUE,
    notification_email VARCHAR(255) DEFAULT NULL,
    identity_status ENUM('unverified', 'pending', 'verified') NOT NULL DEFAULT 'unverified',
    verification_token_hash VARCHAR(255) DEFAULT NULL,
    verification_token_sent_at DATETIME DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    last_seen_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_signal_mailboxes_status (identity_status),
    INDEX idx_signal_mailboxes_notification (notification_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signal_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sender_land_slug VARCHAR(64) NOT NULL,
    sender_land_username VARCHAR(255) NOT NULL,
    sender_virtual_email VARCHAR(255) NOT NULL,
    receiver_land_slug VARCHAR(64) NOT NULL,
    receiver_land_username VARCHAR(255) NOT NULL,
    receiver_virtual_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    delivered_at DATETIME DEFAULT NULL,
    read_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_signal_messages_receiver_unread (receiver_land_slug, read_at, created_at),
    INDEX idx_signal_messages_sender_created (sender_land_slug, created_at),
    INDEX idx_signal_messages_thread (sender_land_slug, receiver_land_slug, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
