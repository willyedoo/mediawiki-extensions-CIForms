-- Table structure for table `CIForms_submissions_groups`

CREATE TABLE CIForms_submissions_groups (
    id SERIAL PRIMARY KEY,
    submission_id INT NOT NULL,
    usergroup VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL
);

-- Indexes for table `CIForms_submissions_groups`
-- (Primary key is already defined with SERIAL)
