-- اضافه کردن ستون avatar به جدول supporters
-- ذخیره مسیر نسبی فایل آپلودشده (مثلاً: "supporters/6543abc.webp")

ALTER TABLE supporters
    ADD COLUMN avatar VARCHAR(255) NULL
    AFTER chat_id;
