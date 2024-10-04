ALTER TABLE CIForms_submissions
    ADD COLUMN username VARCHAR(255);

ALTER TABLE CIForms_submissions
    SET TABLESPACE page_id_ts
    FOR COLUMN page_id;
