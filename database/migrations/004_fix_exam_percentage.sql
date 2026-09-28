-- Migration 004: Fix exam_results.percentage formula
--
-- The legacy stored generated column applied negative marking:
--   ((correct * 3 - wrong) / (total_q * 3)) * 100
-- which under-reported scores (e.g. 3 correct / 9 wrong / 20 total => 0.00
-- instead of the expected 15.00).
--
-- New formula: plain correct ratio, guarding against division by zero.
ALTER TABLE exam_results
    MODIFY COLUMN `percentage` DECIMAL(5,2)
    GENERATED ALWAYS AS (ROUND((`correct` / NULLIF(`total_q`, 0)) * 100, 2)) STORED;
