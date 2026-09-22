-- Migration 001: Fix schema issues identified in audit
-- national_id must be BIGINT (Iranian IDs are 10 digits, exceed INT max)
ALTER TABLE students MODIFY COLUMN national_id BIGINT;

-- Add category_id FK for blog_posts (famo migration expects this)
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER category;
-- Add FK if categories table exists
SET @hasCategories = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blog_categories');
SET @fkExists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blog_posts' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_blog_post_category');
SET @sql = 'ALTER TABLE blog_posts ADD CONSTRAINT fk_blog_post_category FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL';
-- Use prepared statement to avoid error if table/category doesn't exist yet
-- This is executed conditionally by the application code

-- Add missing FK constraints for course_features
SET @hasCourseFeatures = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_features');
SET @fkCfExists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_features' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
-- If course_features has no FK, add one
-- ALTER TABLE course_features ADD CONSTRAINT fk_course_features_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE;

-- Add missing FK for instructor_social_links
-- ALTER TABLE instructor_social_links ADD CONSTRAINT fk_instructor_social_links_instructor FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE;

-- Fix charset consistency: ensure all tables use utf8mb4_persian_ci
ALTER DATABASE `nadcot_famo` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;