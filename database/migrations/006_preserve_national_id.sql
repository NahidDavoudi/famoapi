-- National IDs are identifiers, not numbers. VARCHAR preserves leading zeroes.
ALTER TABLE students MODIFY COLUMN national_id VARCHAR(10) NULL;
