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
-- Add primary key
ALTER TABLE ciforms_submissions
  ADD PRIMARY KEY (id);

-- Add new column
ALTER TABLE ciforms_submissions
  ADD COLUMN username VARCHAR(255) NULL;
--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `CIForms_submissions`
--
ALTER TABLE ciforms_submissions
  ALTER COLUMN id SET DEFAULT nextval('ciforms_submissions_id_seq');
