<?php
/**
 * Database configuration for Affiliate Tracking System
 * 
 * This file contains the database connection settings.
 * IMPORTANT: Update these values with your actual database credentials.
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'affiliate_tracking');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHAR', 'utf8mb4');

// Create PDO connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Please check your configuration.');
}
?>