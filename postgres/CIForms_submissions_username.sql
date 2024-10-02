DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='ciforms_submissions' AND column_name='username') THEN
        ALTER TABLE ciforms_submissions
        ADD COLUMN username VARCHAR(255) NULL;
    END IF;
END $$;
