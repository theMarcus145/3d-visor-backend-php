<?php
// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configuration
define('BASE_PATH', realpath(__DIR__ . '/..'));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('MODELS_PATH', PUBLIC_PATH . '/models');
define('PREVIEWS_PATH', PUBLIC_PATH . '/previews');
define('TEMP_PATH', PUBLIC_PATH . '/temp');
define('MODELS_JSON_PATH', PUBLIC_PATH . '/models.json');

// JWT Configuration
define('JWT_SECRET', $_ENV['JWT_SECRET']);
define('JWT_EXPIRY', 3600); // Token expiry in seconds (1 hour)

// CORS Configuration
define('ALLOWED_ORIGIN', $_ENV['ALLOWED_ORIGIN']);

// Create necessary directories on startup
function ensureDirectories() {
    $dirs = [
        PUBLIC_PATH,
        MODELS_PATH,
        PREVIEWS_PATH,
        MODELS_PATH . '/temp',
        TEMP_PATH
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            error_log("Warning: Could not create directory {$dir}");
        }
    }
    
    // Ensure models.json exists
    if (!file_exists(MODELS_JSON_PATH)) {
        file_put_contents(MODELS_JSON_PATH, json_encode(['models' => []], JSON_PRETTY_PRINT));
        error_log("Initial models.json created");
    }
}

// Initialize directories
ensureDirectories();

// Helper functions
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendError($message, $statusCode = 500) {
    sendResponse(['error' => $message], $statusCode);
}