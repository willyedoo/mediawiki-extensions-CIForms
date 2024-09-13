--
-- Table structure for table CIForms_submissions_groups
--

CREATE TABLE IF NOT EXISTS CIForms_submissions_groups (
  id SERIAL PRIMARY KEY,
  submission_id INTEGER NOT NULL,
  usergroup VARCHAR(255) COLLATE "C" NOT NULL,
  created_at TIMESTAMP NOT NULL
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table CIForms_submissions_groups
--
