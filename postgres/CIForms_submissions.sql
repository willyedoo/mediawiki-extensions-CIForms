-- Table structure for table `CIForms_submissions`

CREATE TABLE IF NOT EXISTS CIForms_submissions (
  id SERIAL PRIMARY KEY,
  page_id INTEGER NOT NULL,
  title VARCHAR(255) COLLATE "C" NOT NULL,
  data BYTEA NOT NULL,
  shown TIMESTAMP DEFAULT NULL,
  created_at TIMESTAMP NOT NULL,
  username VARCHAR(255) NULL
);

-- Indexes for table `CIForms_submissions`
-- (Primary key is already defined with SERIAL)

-- Note: PostgreSQL handles auto-increment with SERIAL type
