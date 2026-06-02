-- ============================================================
-- 008 - additive relational constraints for existing databases
-- ============================================================

DROP PROCEDURE IF EXISTS add_index_if_missing;
DROP PROCEDURE IF EXISTS add_fk_if_missing;

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

CREATE PROCEDURE add_fk_if_missing(
    IN table_name_in VARCHAR(64),
    IN constraint_name_in VARCHAR(64),
    IN ddl_in TEXT
)
BEGIN
    SET @table_exists := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_in
    );

    SET @constraint_exists := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_in
          AND CONSTRAINT_NAME = constraint_name_in
    );

    SET @constraint_sql := IF(@table_exists > 0 AND @constraint_exists = 0, ddl_in, 'SELECT 1');
    PREPARE constraint_stmt FROM @constraint_sql;
    EXECUTE constraint_stmt;
    DEALLOCATE PREPARE constraint_stmt;
END//
DELIMITER ;

CALL add_fk_if_missing(
    'ports',
    'fk_ports_liaison',
    'ALTER TABLE ports ADD CONSTRAINT fk_ports_liaison FOREIGN KEY (liaison_id) REFERENCES liaisons(id) ON DELETE CASCADE'
);

CALL add_fk_if_missing(
    'port_members',
    'fk_port_members_port',
    'ALTER TABLE port_members ADD CONSTRAINT fk_port_members_port FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE'
);

CALL add_fk_if_missing(
    'port_messages',
    'fk_port_messages_port',
    'ALTER TABLE port_messages ADD CONSTRAINT fk_port_messages_port FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE'
);

CALL add_index_if_missing(
    'port_files',
    'unique_stored_name',
    'ALTER TABLE port_files ADD UNIQUE KEY unique_stored_name (stored_name)'
);

CALL add_fk_if_missing(
    'port_files',
    'fk_port_files_port',
    'ALTER TABLE port_files ADD CONSTRAINT fk_port_files_port FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE'
);

CALL add_fk_if_missing(
    'flow_steps',
    'fk_flow_steps_flow',
    'ALTER TABLE flow_steps ADD CONSTRAINT fk_flow_steps_flow FOREIGN KEY (flow_id) REFERENCES flows(id) ON DELETE CASCADE'
);

DROP PROCEDURE IF EXISTS add_index_if_missing;
DROP PROCEDURE IF EXISTS add_fk_if_missing;
