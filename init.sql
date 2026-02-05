CREATE TABLE IF NOT EXISTS lands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email_virtual VARCHAR(255) NOT NULL,
    timezone VARCHAR(255) NOT NULL,
    zone_code VARCHAR(255) NOT NULL,
    shore_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lands_username (username)
);

-- Sowwwl Network Tables

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS identity_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    doc_type ENUM('id_card','passport','driver_license','other') DEFAULT 'other',
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validated_at TIMESTAMP NULL,
    rejected_reason VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending','accepted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request (requester_id, receiver_id),
    FOREIGN KEY (requester_id) REFERENCES lands(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES lands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip (ip_address)
);

-- Identity verification tables

CREATE TABLE IF NOT EXISTS identity_profiles (
    user_id INT PRIMARY KEY,
    phone_e164 VARCHAR(32),
    phone_verified_at TIMESTAMP NULL,
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(120),
    region VARCHAR(120),
    postal_code VARCHAR(40),
    country CHAR(2),
    proof_file VARCHAR(255),
    proof_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    address_verified_at TIMESTAMP NULL,
    postal_code_hash VARCHAR(255),
    postal_code_sent_at TIMESTAMP NULL,
    postal_code_expires_at TIMESTAMP NULL,
    postal_verified_at TIMESTAMP NULL,
    clone_activated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS identity_phone_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone_e164 VARCHAR(32) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES lands(id) ON DELETE CASCADE
);
