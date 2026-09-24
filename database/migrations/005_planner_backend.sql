-- Migration 005: Planner backend support
-- 1) weekly_plans needs timestamps for ordering/summaries.
-- 2) Some imported databases lost the AUTO_INCREMENT primary keys on
--    `events` and `students`; the planner cannot insert without them.
--    Repair them defensively (no-op when already correct).
-- 3) events can carry multiple topics per cell -> child table event_topics.
-- 4) WeeklyPlans module expects a plan_templates table for reusable plans.
-- Safe to re-run.

ALTER TABLE weekly_plans ADD COLUMN IF NOT EXISTS created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE weekly_plans ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- events.id primary key
SET @has_pk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@has_pk = 0, 'ALTER TABLE events ADD PRIMARY KEY (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- events.id auto_increment
SET @has_ai = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%');
SET @sql = IF(@has_ai = 0, 'ALTER TABLE events MODIFY id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- students.id primary key
-- Reassign any placeholder id=0 rows first so the primary key can be added.
SET @max_student_id = (SELECT COALESCE(MAX(id), 0) FROM students);
UPDATE students SET id = (@max_student_id := @max_student_id + 1) WHERE id = 0;

SET @has_pk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@has_pk = 0, 'ALTER TABLE students ADD PRIMARY KEY (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- students.id auto_increment
SET @has_ai = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%');
SET @sql = IF(@has_ai = 0, 'ALTER TABLE students MODIFY id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS event_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    topic_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci NULL,
    topic_label VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci NULL,
    topic_path TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci NULL,
    topic_path_short VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci NULL,
    position INT NOT NULL DEFAULT 0,
    KEY idx_event_topics_event (event_id),
    CONSTRAINT fk_event_topics_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS plan_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    student_id INT NULL,
    week_date VARCHAR(20) NULL,
    items_json LONGTEXT NULL,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
