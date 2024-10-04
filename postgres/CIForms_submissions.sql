-- Table structure for table `CIForms_submissions`
CREATE TABLE CIForms_submissions (
    id SERIAL PRIMARY KEY,
    page_id INT NOT NULL,
    username VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    data BYTEA NOT NULL,
    shown TIMESTAMP,
    created_at TIMESTAMP NOT NULL
);

-- Indexes for table `CIForms_submissions`
-- (Primary key is already defined with SERIAL)
