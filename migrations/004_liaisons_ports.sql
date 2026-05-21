-- ============================================================
--  004 - liaisons + p0rts
--  t0c -> liaisOn/liaisOff + trois formes de partage par p0rt
-- ============================================================

-- Liaisons : etat de connexion entre deux terres
CREATE TABLE IF NOT EXISTS liaisons (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  land_a      VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  land_b      VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  status      ENUM('pending','on','off') NOT NULL DEFAULT 'pending',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_pair (land_a, land_b),
  INDEX idx_land_a (land_a),
  INDEX idx_land_b (land_b),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- P0rts : conteneurs maritimes partages entre terres en liaisOn
CREATE TABLE IF NOT EXISTS ports (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(24) NOT NULL,
  name        VARCHAR(255) DEFAULT NULL,
  liaison_id  INT NOT NULL,
  has_cou12   TINYINT(1) NOT NULL DEFAULT 1,
  has_coeur   TINYINT(1) NOT NULL DEFAULT 1,
  has_core    TINYINT(1) NOT NULL DEFAULT 1,
  html_body   MEDIUMTEXT DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_slug (slug),
  INDEX idx_liaison (liaison_id),
  CONSTRAINT fk_ports_liaison
    FOREIGN KEY (liaison_id) REFERENCES liaisons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membres du p0rt (extensible a plus de 2 terres)
CREATE TABLE IF NOT EXISTS port_members (
  port_id     INT NOT NULL,
  username    VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  cou12_text  TEXT DEFAULT NULL,
  joined_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (port_id, username),
  INDEX idx_port_members_username (username),
  CONSTRAINT fk_port_members_port
    FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages du p0rt (c0eur : salon de discussion)
CREATE TABLE IF NOT EXISTS port_messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  port_id     INT NOT NULL,
  username    VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  body        TEXT NOT NULL,
  type        ENUM('text','html_update','file_upload') NOT NULL DEFAULT 'text',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_port_msg (port_id, created_at),
  INDEX idx_port_messages_user (username, created_at),
  CONSTRAINT fk_port_messages_port
    FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fichiers partages (c0re : echange de fichiers ZIP)
CREATE TABLE IF NOT EXISTS port_files (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  port_id       INT NOT NULL,
  uploaded_by   VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(64)  NOT NULL,
  size_bytes    INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_port_files (port_id),
  INDEX idx_port_files_user (uploaded_by, created_at),
  UNIQUE KEY unique_stored_name (stored_name),
  CONSTRAINT fk_port_files_port
    FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
