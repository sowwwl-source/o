-- ============================================================
-- 007 - additive query indexes for existing databases
-- ============================================================

DROP PROCEDURE IF EXISTS add_index_if_missing;

DELIMITER //
CREATE PROCEDURE add_index_if_missing(
    IN table_name_in VARCHAR(64),
    IN index_name_in VARCHAR(64),
    IN ddl_in TEXT
)
BEGIN
    SET @table_exists := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_in
    );

    SET @idx_exists := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_in
          AND INDEX_NAME = index_name_in
    );

    SET @idx_sql := IF(@table_exists > 0 AND @idx_exists = 0, ddl_in, 'SELECT 1');
    PREPARE idx_stmt FROM @idx_sql;
    EXECUTE idx_stmt;
    DEALLOCATE PREPARE idx_stmt;
END//
DELIMITER ;

CALL add_index_if_missing(
    'echoes',
    'idx_echoes_receiver_unread',
    'CREATE INDEX idx_echoes_receiver_unread ON echoes (receiver_username, is_read, created_at)'
);

CALL add_index_if_missing(
    'echoes',
    'idx_echoes_thread',
    'CREATE INDEX idx_echoes_thread ON echoes (sender_username, receiver_username, created_at)'
);

CALL add_index_if_missing(
    'liaisons',
    'idx_liaisons_land_b_status',
    'CREATE INDEX idx_liaisons_land_b_status ON liaisons (land_b, status, created_at)'
);

CALL add_index_if_missing(
    'port_members',
    'idx_port_members_username',
    'CREATE INDEX idx_port_members_username ON port_members (username)'
);

CALL add_index_if_missing(
    'port_messages',
    'idx_port_messages_user',
    'CREATE INDEX idx_port_messages_user ON port_messages (username, created_at)'
);

CALL add_index_if_missing(
    'port_files',
    'idx_port_files_user',
    'CREATE INDEX idx_port_files_user ON port_files (uploaded_by, created_at)'
);

CALL add_index_if_missing(
    'flow_steps',
    'idx_flow_steps_land',
    'CREATE INDEX idx_flow_steps_land ON flow_steps (land_username)'
);

CALL add_index_if_missing(
    'user_media',
    'idx_user_media_user_created',
    'CREATE INDEX idx_user_media_user_created ON user_media (user_id, created_at)'
);

CALL add_index_if_missing(
    'lands',
    'idx_lands_created_at',
    'CREATE INDEX idx_lands_created_at ON lands (created_at)'
);

DROP PROCEDURE IF EXISTS add_index_if_missing;
