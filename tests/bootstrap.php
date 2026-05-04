<?php
// Simple bootstrap for unit tests - no SilverStripe framework initialization
require_once __DIR__ . '/../../vendor/autoload.php';

// Define constants that might be needed
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/../');
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}
