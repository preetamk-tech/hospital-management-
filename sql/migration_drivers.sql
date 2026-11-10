-- Migration: Add driver role, user_id to ambulances, and requested_at to bookings
-- Run: mysql -u root hospital_queue < this_file.sql

-- 1. Add 'driver' to users.role ENUM
ALTER TABLE `users` 
  MODIFY `role` ENUM('patient','admin','doctor','receptionist','driver') DEFAULT 'patient';

-- 2. Add user_id column to ambulances (links vehicle to driver account)
ALTER TABLE `ambulances` 
  ADD COLUMN IF NOT EXISTS `user_id` INT(11) DEFAULT NULL AFTER `ambulance_id`,
  ADD INDEX `idx_ambulance_user` (`user_id`);

-- 3. Add requested_at column to ambulance_bookings
ALTER TABLE `ambulance_bookings` 
  ADD COLUMN IF NOT EXISTS `requested_at` DATETIME DEFAULT NULL AFTER `created_at`;
