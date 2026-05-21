-- ============================================================
--  005 - fl0w : parcours programmes de terres dans le STR3M
-- ============================================================

CREATE TABLE IF NOT EXISTS flows (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(24) NOT NULL,
  username   VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  name       VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_slug (slug),
  INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flow_steps (
  flow_id       INT NOT NULL,
  position      TINYINT UNSIGNED NOT NULL,
  land_username VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (flow_id, position),
  INDEX idx_flow (flow_id),
  INDEX idx_flow_steps_land (land_username),
  CONSTRAINT fk_flow_steps_flow
    FOREIGN KEY (flow_id) REFERENCES flows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
