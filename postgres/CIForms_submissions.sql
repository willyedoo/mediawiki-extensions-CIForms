--
-- Table structure for table CIForms_submissions
--

CREATE TABLE IF NOT EXISTS ciforms_submissions (
  id SERIAL PRIMARY KEY,
  page_id INTEGER NOT NULL,
  title VARCHAR(255) COLLATE "C" NOT NULL,
  data BYTEA NOT NULL,
  shown TIMESTAMP DEFAULT NULL,
  created_at TIMESTAMP NOT NULL,
  username VARCHAR(255) NULL
);

--
-- Indexes for dumped tables
--
