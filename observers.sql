CREATE TABLE IF NOT EXISTS `observers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `voting_center_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_voting_center` (`voting_center_name`),
    INDEX `idx_mobile` (`mobile`)
);

-- نموونەی زانیاری بۆ تاقیکردنەوە
INSERT IGNORE INTO `observers` (`full_name`, `mobile`, `password`, `voting_center_name`) VALUES
('ئاحمەد حەسەن', '07501111111', '123456', 'بنکی ناوەندی'),
('فاتیمە عەلی', '07502222222', '123456', 'بنکی ڕەواندز'),
('محەممەد ئیبراهیم', '07503333333', '123456', 'بنکی سلێمانی'),
('زەینەب کەریم', '07504444444', '123456', 'بنکی هەولێر'),
('یوسف مستەفا', '07505555555', '123456', 'بنکی دهۆک');