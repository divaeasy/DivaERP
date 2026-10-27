-- Migration SQL (phpMyAdmin/OVH) - Version20260413121500
-- Replace entetepiece.client_id by entetepiece.tier_id (safe/idempotent)

SET @db := DATABASE();

-- 1) Add tier_id if missing
SET @has_tier_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'entetepiece'
    AND COLUMN_NAME = 'tier_id'
);
SET @sql := IF(@has_tier_col = 0,
  'ALTER TABLE entetepiece ADD COLUMN tier_id INT NULL',
  'SELECT "tier_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Add index on tier_id if missing
SET @has_tier_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'entetepiece'
    AND INDEX_NAME = 'IDX_ENTETEPIECE_TIER_ID'
);
SET @sql := IF(@has_tier_idx = 0,
  'CREATE INDEX IDX_ENTETEPIECE_TIER_ID ON entetepiece (tier_id)',
  'SELECT "IDX_ENTETEPIECE_TIER_ID already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Copy existing client_id values to tier_id for typet Client
SET @has_client_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'entetepiece'
    AND COLUMN_NAME = 'client_id'
);
SET @sql := IF(@has_client_col = 1,
  "UPDATE entetepiece SET tier_id = client_id WHERE tier_id IS NULL AND LOWER(COALESCE(typet, '')) = 'client'",
  'SELECT "client_id not found, skip data copy"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Drop FK on client_id if it exists
SET @fk_name := (
  SELECT k.CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE k
  WHERE k.TABLE_SCHEMA = @db
    AND k.TABLE_NAME = 'entetepiece'
    AND k.COLUMN_NAME = 'client_id'
    AND k.REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1
);
SET @sql := IF(@fk_name IS NOT NULL,
  CONCAT('ALTER TABLE entetepiece DROP FOREIGN KEY `', @fk_name, '`'),
  'SELECT "No FK found on client_id"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) Drop non-primary index on client_id if any
SET @idx_name := (
  SELECT s.INDEX_NAME
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = @db
    AND s.TABLE_NAME = 'entetepiece'
    AND s.COLUMN_NAME = 'client_id'
    AND s.INDEX_NAME <> 'PRIMARY'
  LIMIT 1
);
SET @sql := IF(@idx_name IS NOT NULL,
  CONCAT('DROP INDEX `', @idx_name, '` ON entetepiece'),
  'SELECT "No index found on client_id"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6) Drop client_id column if still present
SET @has_client_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'entetepiece'
    AND COLUMN_NAME = 'client_id'
);
SET @sql := IF(@has_client_col = 1,
  'ALTER TABLE entetepiece DROP COLUMN client_id',
  'SELECT "client_id already removed"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
