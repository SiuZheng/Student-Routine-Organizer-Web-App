<?php
/**
 * Admin Configuration File
 * This file contains hardcoded admin credentials and settings
 * 
 * WARNING: Keep this file secure and don't commit it to public repositories
 */

return [
    // Hardcoded Admin Credentials - Primary admin access
    'hardcoded_admin' => [
        'email' => 'lee@gmail.com',
        'password' => '123',
        'username' => 'Lee (Admin)',
        'role' => 'super_admin'
    ],
    
    // Admin Access Settings
    'admin_settings' => [
        'session_timeout' => 3600, // 1 hour in seconds
        'max_login_attempts' => 5,
        'require_2fa' => false, // Set to true for additional security
    ],
    
    // Admin Capabilities
    'capabilities' => [
        'view_all_users' => true,
        'delete_users' => true,
        'view_system_stats' => true,
        'view_financial_data' => true,
        'view_activity_logs' => true,
        'manage_system_settings' => true
    ]
];
?>
