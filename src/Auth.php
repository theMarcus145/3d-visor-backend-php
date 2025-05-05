<?php
namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    // Hardcoded users (same as in original)
    private static $users = [
        [
            'username' => 'admin',
            'passwordHash' => '$2a$10$kHM6nNrIpnHOQyYpel0hBO521.ZjRCDo0cNg3URHILB3JLkPEVjO2'
        ]
    ];
    
    /**
     * Verify user credentials
     * 
     * @param string $username Username
     * @param string $plainPassword Plain text password
     * @return bool True if credentials are valid
     */
    public static function verifyCredentials($username, $plainPassword) {
        error_log("Verificando credenciales para: {$username}");
        
        $user = null;
        foreach (self::$users as $u) {
            if ($u['username'] === $username) {
                $user = $u;
                break;
            }
        }
        
        if (!$user) {
            error_log('Usuario no encontrado');
            return false;
        }
        
        $isMatch = password_verify($plainPassword, $user['passwordHash']);
        error_log("Resultado de verificación de contraseña: " . ($isMatch ? 'true' : 'false'));
        return $isMatch;
    }
    
    /**
     * Generate JWT token
     * 
     * @param string $username Username
     * @return string Generated JWT token
     */
    public static function generateToken($username) {
        $payload = [
            'username' => $username,
            'exp' => time() + JWT_EXPIRY
        ];
        
        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }
    
    /**
     * Verify JWT token
     * 
     * @param string $token JWT token
     * @return object|null Decoded token payload or null if invalid
     */
    public static function verifyToken($token) {
        try {
            return JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        } catch (\Exception $e) {
            error_log("Token verification failed: " . $e->getMessage());
            return null;
        }
    }
}