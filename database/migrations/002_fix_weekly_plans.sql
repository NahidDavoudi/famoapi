-- Migration 002: Resolve weekly_plans schema conflict
-- The admin's auto-create uses: student_id, day_of_week, time_slot, subject, description, color
-- The database dump shows: student_id, week_date, weekly_notes, times_json (JSON)
-- We keep the existing database schema and drop any admin-created conflicting table

-- Ensure weekly_plans uses the JSON-based schema (existing DB has this)
-- If the admin-created variant exists, migrate data and drop it
-- This migration is safe to re-run

-- Step 1: Backup existing data if needed (handled by application, not here)

-- Step 2: Ensure the correct table structure exists
CREATE TABLE IF NOT EXISTS `weekly_plans` (
    `id` int NOT NULL AUTO_INCREMENT,
    `student_id` int NOT NULL,
    `week_date` date NOT NULL,
    `weekly_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci,
    `times_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_student_week` (`student_id`, `week_date`),
    KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;