ALTER TABLE "CIForms_submissions"
ADD COLUMN "username" VARCHAR(255) NULL;

ALTER TABLE "CIForms_submissions"
ADD CONSTRAINT fk_page
FOREIGN KEY ("page_id")
REFERENCES "Pages" ("page_id");
