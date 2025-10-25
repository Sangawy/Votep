CREATE TABLE IF NOT EXISTS `voters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `card_number` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20),
    `voting_center_name` VARCHAR(100) NOT NULL,
    `status` ENUM('not_voted', 'voted') DEFAULT 'not_voted',
    `scanned_at` TIMESTAMP NULL,
    `scanned_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_voting_center` (`voting_center_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_card_number` (`card_number`),
    FOREIGN KEY (`scanned_by`) REFERENCES `observers`(`id`) ON DELETE SET NULL
);

-- نموونەی زانیاری بۆ تاقیکردنەوە
INSERT IGNORE INTO `voters` (`card_number`, `full_name`, `phone`, `voting_center_name`, `status`) VALUES
('C001V001', 'عەلی محەممەد', '07501230001', 'بنکی ناوەندی', 'not_voted'),
('C001V002', 'سارا عەبدوڵڵا', '07501230002', 'بنکی ناوەندی', 'not_voted'),
('C001V003', 'حوسێن ڕەسووڵ', '07501230003', 'بنکی ناوەندی', 'voted'),
('C002V001', 'نازنین عەلی', '07501230004', 'بنکی ڕەواندز', 'not_voted'),
('C002V002', 'کەریم مستەفا', '07501230005', 'بنکی ڕەواندز', 'voted');