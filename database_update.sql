-- زیادکردنی خشتە نوێیەکان بۆ سیستەمی بەڕێوەبردنی کتێبخانە
-- ئەمە تەنها خشتە نوێیەکان زیاد دەکات، داتاکانی پێشوو دەپارێزێت

-- Authors table (ئەگەر نەبوو دروستی بکە)
CREATE TABLE IF NOT EXISTS authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Translators table (ئەگەر نەبوو دروستی بکە)
CREATE TABLE IF NOT EXISTS translators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Check if author_id column exists in books, if not add it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'books' 
    AND COLUMN_NAME = 'author_id'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE books ADD COLUMN author_id INT AFTER title', 
    'SELECT ''Column already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if translator_id column exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'books' 
    AND COLUMN_NAME = 'translator_id'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE books ADD COLUMN translator_id INT AFTER author_id', 
    'SELECT ''Column already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if shelf_code column exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'books' 
    AND COLUMN_NAME = 'shelf_code'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE books ADD COLUMN shelf_code VARCHAR(100) AFTER quantity', 
    'SELECT ''Column already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if book_code column exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'books' 
    AND COLUMN_NAME = 'book_code'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE books ADD COLUMN book_code VARCHAR(100) UNIQUE AFTER shelf_code', 
    'SELECT ''Column already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if language column exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'books' 
    AND COLUMN_NAME = 'language'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE books ADD COLUMN language VARCHAR(50) AFTER publish_year', 
    'SELECT ''Column already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign keys if they don't exist
ALTER TABLE books ADD FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL;
ALTER TABLE books ADD FOREIGN KEY (translator_id) REFERENCES translators(id) ON DELETE SET NULL;

