<?php
// Error reporting for development (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('UTC');

// Autoload dependencies
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config.php';

use App\Auth;
use App\UploadController;
use App\Middleware\AuthMiddleware;

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: " . ALLOWED_ORIGIN);
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    exit(0);
}

// Set CORS headers for all responses
header("Access-Control-Allow-Origin: " . ALLOWED_ORIGIN);
header("Content-Type: application/json");

// Parse the request URL
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$path = substr($requestUri, strlen($basePath));
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Route requests
switch (true) {
    // API Status check
    case $path === 'api/status':
        sendResponse([
            'status' => 'ok',
            'message' => 'Servidor en funcionamiento'
        ]);
        break;
        
    // Login endpoint
    case $method === 'POST' && $path === 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            sendError('Se requiere usuario y contraseña', 400);
        }
        
        $username = $data['username'];
        $password = $data['password'];
        
        error_log("Solicitud de inicio de sesión: {$username}");
        
        if (Auth::verifyCredentials($username, $password)) {
            $token = Auth::generateToken($username);
            sendResponse([
                'message' => 'Autenticación exitosa',
                'token' => $token
            ]);
        } else {
            sendError('Credenciales inválidas', 401);
        }
        break;
        
    // Verify token endpoint
    case $method === 'GET' && $path === 'verify-token':
        $decoded = AuthMiddleware::handle();
        
        if ($decoded) {
            sendResponse([
                'valid' => true,
                'user' => $decoded->username
            ]);
        }
        break;
        
    // Upload model endpoint
    case $method === 'POST' && $path === 'upload':
        // Verify token
        $decoded = AuthMiddleware::handle();
        if (!$decoded) {
            break;
        }
        
        // Process upload
        $modelName = $_POST['modelName'] ?? '';
        
        if (empty($modelName)) {
            sendError('Se requiere el nombre del modelo', 400);
        }
        
        error_log("Solicitud de subida recibida para modelo: {$modelName}");
        
        if (empty($_FILES['model']) || empty($_FILES['preview'])) {
            sendError('Se requieren ambos archivos: modelo y previsualización', 400);
        }
        
        $result = UploadController::uploadFiles($_FILES, $modelName);
        
        if ($result) {
            error_log("Subida exitosa: {$modelName}");
            sendResponse([
                'message' => 'Modelo correctamente subido',
                'model' => $result
            ]);
        } else {
            sendError('Error al subir el modelo', 500);
        }
        break;
        
    // Delete model endpoint
    case $method === 'POST' && $path === 'delete-model':
        // Verify token
        $decoded = AuthMiddleware::handle();
        if (!$decoded) {
            break;
        }
        
        // Process deletion
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['modelName']) || !isset($data['modelPath']) || !isset($data['imagePath'])) {
            sendError('Falta información del modelo', 400);
        }
        
        $modelName = $data['modelName'];
        $modelPath = $data['modelPath'];
        $imagePath = $data['imagePath'];
        
        error_log("Solicitud de eliminación recibida: {$modelName}");
        
        $result = UploadController::deleteModel($modelName, $modelPath, $imagePath);
        
        if ($result) {
            sendResponse([
                'message' => "Modelo \"{$modelName}\" eliminado correctamente",
                'deleted' => $result
            ]);
        } else {
            sendError('Fallo al eliminar el modelo', 500);
        }
        break;
        
    // Serve models.json
    case $method === 'GET' && $path === 'models.json':
        $modelsJsonPath = MODELS_JSON_PATH;
        if (file_exists($modelsJsonPath)) {
            header('Content-Type: application/json');
            readfile($modelsJsonPath);
            exit;
        } else {
            sendError('Archivo no encontrado', 404);
        }
        break;
        
    // Serve static files
    case preg_match('/^(models|previews)\//', $path):
        $filePath = PUBLIC_PATH . '/' . $path;
        if (file_exists($filePath)) {
            // Set content type based on extension
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            switch ($extension) {
                case 'glb':
                    header('Content-Type: model/gltf-binary');
                    break;
                case 'png':
                    header('Content-Type: image/png');
                    break;
                case 'jpg':
                case 'jpeg':
                    header('Content-Type: image/jpeg');
                    break;
                case 'webp':
                    header('Content-Type: image/webp');
                    break;
                default:
                    header('Content-Type: application/octet-stream');
            }
            readfile($filePath);
            exit;
        } else {
            sendError('Archivo no encontrado', 404);
        }
        break;
        
    // Default: 404 Not Found
    default:
        sendError('Ruta no encontrada', 404);
}