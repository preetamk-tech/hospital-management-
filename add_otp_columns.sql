ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `otp_code` varchar(6) DEFAULT NULL AFTER `reset_expires`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `otp_expires` datetime DEFAULT NULL AFTER `otp_code`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `is_verified` tinyint(1) DEFAULT 0 AFTER `otp_expires`;

-- Update existing users as verified
UPDATE `users` SET `is_verified` = 1 WHERE `is_verified` IS NULL;
