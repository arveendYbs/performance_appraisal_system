<?php
// config/config.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Application Configuration
define('APP_NAME', 'Performance Appraisal System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/performance_appraisal_system');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'performance_appraisal_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security Configuration
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', 3600); // 1 hour

// File Upload Configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Pagination
define('RECORDS_PER_PAGE', 10);

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files first
require_once __DIR__ . '/../includes/functions.php';

// Autoload classes
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Include database after autoloading
require_once __DIR__ . '/../classes/Database.php';
?>