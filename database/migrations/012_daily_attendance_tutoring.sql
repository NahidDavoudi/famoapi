-- Migration 012: daily attendance + tutoring foundation.
--
-- The legacy users and instructors tables on deployed databases do not always
-- carry a primary key/index on id (see migration 008/011 notes). InnoDB requires
-- the referenced columns of a foreign key to be indexed, so ensure those indexes
-- exist before creating the FK-bearing tables below. Idempotent and additive.

ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_id (id);
ALTER TABLE instructors ADD INDEX IF NOT EXISTS idx_instructors_id (id);

ALTER TABLE users
  MODIFY role ENUM('admin','supporter','student','teacher') NOT NULL;

CREATE TABLE IF NOT EXISTS tutoring_classrooms (
  id            INT NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NOT NULL,
  instructor_id INT NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classroom_instructor (instructor_id),
  CONSTRAINT fk_classroom_instructor FOREIGN KEY (instructor_id) REFERENCES instructors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_attendance (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attendance_date DATE NOT NULL,
  student_id      INT NULL,
  guest_name      VARCHAR(255) NULL,
  field           VARCHAR(50) NULL,
  status          ENUM('present','absent') NOT NULL DEFAULT 'present',
  arrived_at      DATETIME NULL,
  exam_started_at DATETIME NULL,
  exam_ended_at   DATETIME NULL,
  departed_at     DATETIME NULL,
  removed_at      DATETIME NULL,
  removed_by      INT NULL,
  created_by      INT NULL,
  client_uuid     CHAR(36) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_att_student_day (attendance_date, student_id),
  UNIQUE KEY uq_att_client_uuid (client_uuid),
  KEY idx_att_day_field (attendance_date, field),
  CONSTRAINT fk_att_student    FOREIGN KEY (student_id)  REFERENCES students (id),
  CONSTRAINT fk_att_removed_by FOREIGN KEY (removed_by)  REFERENCES users (id),
  CONSTRAINT fk_att_created_by FOREIGN KEY (created_by)  REFERENCES users (id),
  CONSTRAINT chk_att_identity  CHECK (student_id IS NOT NULL OR guest_name IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutoring_sessions (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_date      DATE NOT NULL,
  classroom_id      INT NOT NULL,
  student_id        INT NOT NULL,
  attendance_id     BIGINT UNSIGNED NULL,
  entered_at        DATETIME NOT NULL,
  ended_at          DATETIME NULL,
  client_entered_at DATETIME NULL,
  entry_source      ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  needs_review      TINYINT(1) NOT NULL DEFAULT 0,
  created_by        INT NULL,
  client_uuid       CHAR(36) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  open_student_key  INT AS (IF(ended_at IS NULL, student_id, NULL)) VIRTUAL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sess_client_uuid (client_uuid),
  UNIQUE KEY uq_sess_one_open_per_student (open_student_key),
  KEY idx_sess_day_class (session_date, classroom_id),
  KEY idx_sess_class_open (classroom_id, ended_at),
  CONSTRAINT fk_sess_classroom  FOREIGN KEY (classroom_id)  REFERENCES tutoring_classrooms (id),
  CONSTRAINT fk_sess_student    FOREIGN KEY (student_id)    REFERENCES students (id),
  CONSTRAINT fk_sess_attendance FOREIGN KEY (attendance_id) REFERENCES daily_attendance (id),
  CONSTRAINT fk_sess_created_by FOREIGN KEY (created_by)    REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutoring_teacher_status_log (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instructor_id INT NOT NULL,
  status        ENUM('absent','ready','break') NOT NULL,
  status_date   DATE NOT NULL,
  started_at    DATETIME NOT NULL,
  ended_at      DATETIME NULL,
  client_uuid   CHAR(36) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_status_client_uuid (client_uuid),
  KEY idx_status_teacher_day (instructor_id, status_date, ended_at),
  CONSTRAINT fk_status_instructor FOREIGN KEY (instructor_id) REFERENCES instructors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
