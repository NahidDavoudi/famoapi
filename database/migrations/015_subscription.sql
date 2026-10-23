CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id INT NOT NULL,
  student_id INT NOT NULL,
  status ENUM ('paid', 'pending', 'cancelled', 'not paid'),
  is_active ENUM ('0' , '1'),
  note TEXT DEFAULT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  end_at DATETIME,
  PRIMARY KEY (id),
  KEY student_subscription (student_id),
  KEY course_subscription (course_id),
  CONSTRAINT fk_student_subscription FOREIGN KEY (student_id) REFERENCES students (id),
  CONSTRAINT fk_course_subscription FOREIGN KEY (course_id) REFERENCES courses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- WE NEED TO NORMALIZE THE ID'S SO ALL HAVE THE SAME TYPE, BIGINT