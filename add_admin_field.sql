-- Add admin field to users table
-- Run this script to enable admin functionality

USE student_routine_db;

-- Add is_admin column to users table
ALTER TABLE users 
ADD COLUMN is_admin BOOLEAN DEFAULT FALSE AFTER password;

-- Verify the change
SELECT id, username, email, is_admin FROM users;
