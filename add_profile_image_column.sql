-- Add profile_image column to users table
ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER passport_number; 