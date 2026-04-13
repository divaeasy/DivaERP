-- Migration SQL for hosted MySQL/phpMyAdmin
-- Adds the new optional image column to the article table.

ALTER TABLE `article`
  ADD COLUMN `image` VARCHAR(255) NULL;
