CREATE TABLE IF NOT EXISTS lands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email_virtual VARCHAR(255) NOT NULL,
    timezone VARCHAR(255) NOT NULL,
    zone_code VARCHAR(255) NOT NULL,
    shore_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lands_username (username)
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45)
);

CREATE TABLE IF NOT EXISTS connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_id INT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_connection (user_id, target_id),
    FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES lands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE,
    INDEX idx_posts_created_at (created_at DESC)
);

CREATE TABLE IF NOT EXISTS workspaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    owner_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES lands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS p0sts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    workspace_id INT NOT NULL,
    content JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_3d_spaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    container_type ENUM('iso_20ft', 'iso_40ft', 'data_pod', 'glass_tank') DEFAULT 'iso_20ft',
    transport_status ENUM('docked', 'on_f3rry') DEFAULT 'docked',
    f3rry_id INT NULL,
    config_json JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    space_id INT NOT NULL,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    media_type ENUM('image', 'video', 'audio', 'model_3d', 'text') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    is_public BOOLEAN DEFAULT FALSE,
    placement_json JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE,
    FOREIGN KEY (space_id) REFERENCES user_3d_spaces(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS kb_chunks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    workspace_id INT NOT NULL,
    content TEXT,
    embedding JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
);
