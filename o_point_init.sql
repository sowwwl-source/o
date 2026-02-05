-- O.point : Initialisation SQL complète (v1)

-- 1. FOYER (users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    email_virtual VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    bi TEXT,
    avatar VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- 2. PORT (ports)
CREATE TABLE IF NOT EXISTS ports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scope ENUM('foyer','hallway','o') DEFAULT 'foyer',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_scope (scope)
);

-- 3. HALLWAY (hallways)
CREATE TABLE IF NOT EXISTS hallways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_open BOOLEAN DEFAULT TRUE,
    is_temporary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_open (is_open)
);

-- 4. O (o_surface)
CREATE TABLE IF NOT EXISTS o_surface (
    id INT AUTO_INCREMENT PRIMARY KEY,
    port_id INT NOT NULL,
    visible_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    visible_until TIMESTAMP NULL,
    FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE,
    INDEX idx_visible (visible_from, visible_until)
);

-- 5. PRESENCE POINTS (optionnel)
CREATE TABLE IF NOT EXISTS presence_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type ENUM('ha','error') DEFAULT 'ha',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. SEED EXEMPLE (optionnel)
INSERT INTO users (username, email_virtual, password_hash, bi, avatar) VALUES
('alice', 'alice@o.local', 'hash1', 'Ici, je respire.', NULL),
('bob', 'bob@o.local', 'hash2', 'Un matin, quelqu’un a laissé un mot.', NULL);

INSERT INTO ports (user_id, content, scope) VALUES
(1, 'Ici, la lumière a glissé sur la surface.', 'o'),
(2, 'Porte sur le matin', 'foyer');

INSERT INTO hallways (from_user_id, to_user_id, is_open) VALUES
(1, 2, TRUE);

INSERT INTO o_surface (port_id, visible_from) VALUES
(1, NOW());

-- Fin du script O.point v1
