--
-- Table structure for table CIForms_submissions_groups
--

CREATE TABLE IF NOT EXISTS ciforms_submissions (
  id SERIAL PRIMARY KEY,
  page_id INTEGER NOT NULL,
  title VARCHAR(255) COLLATE "C" NOT NULL,
  data BYTEA NOT NULL,
  shown TIMESTAMP DEFAULT NULL,
  created_at TIMESTAMP NOT NULL
);
--
-- Indexes for dumped tables
--

--
-- Indexes for table CIForms_submissions_groups
--
