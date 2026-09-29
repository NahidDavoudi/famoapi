-- Migration 001: Fix schema issues identified in audit
-- national_id is an identifier. Keep its width and leading zeroes (migration 006
-- performs the final string conversion for databases that already ran 001).

-- Add category_id FK for blog_posts (famo migration expects this)
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER category;
-- Add FK if categories table exists
SET @hasCategories = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blog_categories');
SET @fkExists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blog_posts' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_blog_post_category');
SET @sql = 'ALTER TABLE blog_posts ADD CONSTRAINT fk_blog_post_category FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL';
SET @sql = IF(@hasCategories > 0 AND @fkExists = 0, @sql, 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add missing FK constraints for course_features
SET @hasCourseFeatures = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_features');
SET @fkCfExists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_features' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
-- If course_features has no FK, add one
SET @sql = IF(@hasCourseFeatures > 0 AND @fkCfExists = 0,
    'ALTER TABLE course_features ADD CONSTRAINT fk_course_features_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add missing FK for instructor_social_links
SET @hasInstructorSocialLinks = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'instructor_social_links');
SET @fkInstructorExists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'instructor_social_links' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_instructor_social_links_instructor');
SET @sql = IF(@hasInstructorSocialLinks > 0 AND @fkInstructorExists = 0,
    'ALTER TABLE instructor_social_links ADD CONSTRAINT fk_instructor_social_links_instructor FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fix charset consistency: ensure all tables use utf8mb4_persian_ci
SET @database_name = REPLACE(DATABASE(), '`', '``');
SET @sql = CONCAT('ALTER DATABASE `', @database_name, '` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
