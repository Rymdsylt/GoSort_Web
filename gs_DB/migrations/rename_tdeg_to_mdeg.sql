-- Migration: rename column tdeg to mdeg in sorter_mapping if exists
ALTER TABLE sorter_mapping
  CHANGE COLUMN tdeg mdeg VARCHAR(10) NULL;

-- If the column doesn't exist, this will error; run manually after checking current schema.
