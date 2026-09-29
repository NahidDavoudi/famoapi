-- Repair identifiers and add a lookup index on the existing parent_contacts table.
-- MariaDB does not support conditional ALTER TABLE uniformly across deployed versions,
-- so inspect information_schema and execute only the required DDL.

SET @parent_contacts_has_primary_key = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'parent_contacts'
      AND CONSTRAINT_TYPE = 'PRIMARY KEY'
);
SET @parent_contacts_primary_sql = IF(
    @parent_contacts_has_primary_key = 0,
    'ALTER TABLE parent_contacts ADD PRIMARY KEY (id)',
    'SELECT 1'
);
PREPARE parent_contacts_primary_stmt FROM @parent_contacts_primary_sql;
EXECUTE parent_contacts_primary_stmt;
DEALLOCATE PREPARE parent_contacts_primary_stmt;

SET @parent_contacts_id_auto_increment = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'parent_contacts'
      AND COLUMN_NAME = 'id'
      AND EXTRA LIKE '%auto_increment%'
);
SET @parent_contacts_auto_increment_sql = IF(
    @parent_contacts_id_auto_increment = 0,
    'ALTER TABLE parent_contacts MODIFY id INT(11) NOT NULL AUTO_INCREMENT',
    'SELECT 1'
);
PREPARE parent_contacts_auto_increment_stmt FROM @parent_contacts_auto_increment_sql;
EXECUTE parent_contacts_auto_increment_stmt;
DEALLOCATE PREPARE parent_contacts_auto_increment_stmt;

SET @parent_contacts_student_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'parent_contacts'
      AND INDEX_NAME = 'idx_parent_contacts_student'
);
SET @parent_contacts_index_sql = IF(
    @parent_contacts_student_index = 0,
    'ALTER TABLE parent_contacts ADD INDEX idx_parent_contacts_student (student_id)',
    'SELECT 1'
);
PREPARE parent_contacts_index_stmt FROM @parent_contacts_index_sql;
EXECUTE parent_contacts_index_stmt;
DEALLOCATE PREPARE parent_contacts_index_stmt;
