-- Table structure for table CIForms_submissions

CREATE TABLE IF NOT EXISTS ciforms_submissions (
  id SERIAL PRIMARY KEY,
  page_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  data BYTEA NOT NULL,
  shown TIMESTAMP DEFAULT NULL,
  created_at TIMESTAMP NOT NULL
);

-- Adding the username column
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='ciforms_submissions' AND column_name='username') THEN
        ALTER TABLE ciforms_submissions 
        ADD COLUMN username VARCHAR(255) NULL;
    END IF;
END $$;
