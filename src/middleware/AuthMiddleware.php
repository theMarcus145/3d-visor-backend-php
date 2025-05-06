<?php
namespace App\Middleware;

use App\Auth;

class AuthMiddleware {
    /**
     * Check if the request has a valid token
     * 
     * @return bool|object Returns false if unauthorized, otherwise returns the decoded token
     */
    public static function handle() {
        // Get authorization header
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            sendError('Acceso no autorizado', 401);
            return false;
        }
        
        // Extract token
        $token = substr($authHeader, 7);
        $decoded = Auth::verifyToken($token);
        
        if (!$decoded) {
            sendError('Token inválido o expirado', 401);
            return false;
        }
        
        return $decoded;
    }
}

// PHP 7.4 compatibility for str_starts_with if it doesn't exist
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}