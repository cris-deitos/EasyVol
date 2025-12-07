-- Create backup of existing member_availability table if it exists
-- This allows rollback if needed
CREATE TABLE IF NOT EXISTS member_availability_backup_20241207 AS 
SELECT * FROM member_availability WHERE 1=0;

INSERT INTO member_availability_backup_20241207 
SELECT * FROM member_availability WHERE EXISTS (
    SELECT 1 FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'member_availability'
);

-- Drop existing member_availability table if it exists with wrong schema
DROP TABLE IF EXISTS member_availability;

-- Create member_availability table for tracking volunteer availability status
CREATE TABLE IF NOT EXISTS member_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    availability_type ENUM('available', 'limited', 'unavailable') DEFAULT 'available',
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (member_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: If the old table had a different schema with data you want to migrate,
-- restore from member_availability_backup_20241207 and map the fields manually
