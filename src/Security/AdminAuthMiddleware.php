<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Logging\Logger;

/**
 * Admin Authentication Middleware
 * Provides authentication and authorization for admin endpoints.
 * Supports API token authentication and session-based authentication.
 */
class AdminAuthMiddleware
{
    /**
     * Admin session key.
     */
    private const SESSION_KEY = 'ghidar_admin_session';
    
    /**
     * Session expiry time in seconds (8 hours).
     */
    private const SESSION_EXPIRY = 28800;
    
    /**
     * Maximum failed login attempts before lockout.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;
    
    /**
     * Lockout duration in seconds (15 minutes).
     */
    private const LOCKOUT_DURATION = 900;
    
    /**
     * Admin roles and their permissions.
     */
    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'admin' => [
            'view_users',
            'manage_users',
            'view_withdrawals',
            'manage_withdrawals',
            'view_deposits',
            'view_verifications',
            'manage_verifications',
            'view_lottery',
            'manage_lottery',
            'view_statistics',
            'view_logs',
        ],
        'moderator' => [
            'view_users',
            'view_withdrawals',
            'view_deposits',
            'view_verifications',
            'view_lottery',
            'view_statistics',
        ],
        'viewer' => [
            'view_users',
            'view_statistics',
        ],
    ];
    
    /**
     * Require admin authentication.
     * Call this at the start of admin endpoints.
     * 
     * @param string|null $requiredPermission Optional specific permission to check
     * @return array{id: int, username: string, role: string} Admin user data
     * @throws \RuntimeException If authentication fails
     */
    public static function requireAuth(?string $requiredPermission = null): array
    {
        // Check for API token first
        $apiToken = self::getApiToken();
        if ($apiToken !== null) {
            return self::validateApiToken($apiToken, $requiredPermission);
        }
        
        // Check for session-based auth
        return self::validateSession($requiredPermission);
    }
    
    /**
     * Get API token from headers.
     */
    private static function getApiToken(): ?string
    {
        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Check custom header
        $customToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
        if ($customToken !== null) {
            return $customToken;
        }
        
        return null;
    }
    
    /**
     * Validate API token.
     */
    private static function validateApiToken(string $token, ?string $requiredPermission): array
    {
        try {
            $pdo = Database::ensureConnection();
            
            // Check if token exists and is not expired
            $stmt = $pdo->prepare("
                SELECT at.*, a.id as admin_id, a.username, a.role, a.is_active
                FROM admin_api_tokens at
                JOIN admin_users a ON at.admin_id = a.id
                WHERE at.token_hash = :hash
                AND (at.expires_at IS NULL OR at.expires_at > NOW())
                AND a.is_active = 1
                LIMIT 1
            ");
            $stmt->execute(['hash' => hash('sha256', $token)]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result === false) {
                Logger::warning('admin_invalid_api_token', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                throw new \RuntimeException('Invalid or expired API token');
            }
            
            // Update last used timestamp
            $updateStmt = $pdo->prepare("
                UPDATE admin_api_tokens 
                SET last_used_at = NOW(), use_count = use_count + 1 
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $result['id']]);
            
            $adminData = [
                'id' => (int)$result['admin_id'],
                'username' => $result['username'],
                'role' => $result['role'],
            ];
            
            // Check permission
            if ($requiredPermission !== null) {
                self::checkPermission($adminData['role'], $requiredPermission);
            }
            
            return $adminData;
            
        } catch (\PDOException $e) {
            // Table might not exist yet - fallback to environment token
            return self::validateEnvToken($token, $requiredPermission);
        }
    }
    
    /**
     * Validate token from environment variable (simple setup).
     */
    private static function validateEnvToken(string $token, ?string $requiredPermission): array
    {
        $envToken = Config::get('ADMIN_API_TOKEN');
        
        if ($envToken === null || !hash_equals($envToken, $token)) {
            throw new \RuntimeException('Invalid API token');
        }
        
        // Environment token always has super_admin role
        return [
            'id' => 0,
            'username' => 'api_admin',
            'role' => 'super_admin',
        ];
    }
    
    /**
     * Validate session-based authentication.
     */
    private static function validateSession(?string $requiredPermission): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionData = $_SESSION[self::SESSION_KEY] ?? null;
        
        if ($sessionData === null) {
            throw new \RuntimeException('Admin authentication required');
        }
        
        // Check session expiry
        if (time() > $sessionData['expires_at']) {
            unset($_SESSION[self::SESSION_KEY]);
            throw new \RuntimeException('Admin session expired');
        }
        
        // Verify session is still valid in database
        try {
            $pdo = Database::ensureConnection();
            
            $stmt = $pdo->prepare("
                SELECT id, username, role, is_active 
                FROM admin_users 
                WHERE id = :id AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute(['id' => $sessionData['admin_id']]);
            $admin = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($admin === false) {
                unset($_SESSION[self::SESSION_KEY]);
                throw new \RuntimeException('Admin account disabled');
            }
            
            $adminData = [
                'id' => (int)$admin['id'],
                'username' => $admin['username'],
                'role' => $admin['role'],
            ];
            
        } catch (\PDOException $e) {
            // Table might not exist - use session data
            $adminData = [
                'id' => $sessionData['admin_id'],
                'username' => $sessionData['username'],
                'role' => $sessionData['role'] ?? 'admin',
            ];
        }
        
        // Check permission
        if ($requiredPermission !== null) {
            self::checkPermission($adminData['role'], $requiredPermission);
        }
        
        // Extend session
        $_SESSION[self::SESSION_KEY]['expires_at'] = time() + self::SESSION_EXPIRY;
        
        return $adminData;
    }
    
    /**
     * Check if a role has a specific permission.
     */
    private static function checkPermission(string $role, string $permission): void
    {
        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];
        
        if (in_array('*', $permissions, true)) {
            return; // Super admin has all permissions
        }
        
        if (!in_array($permission, $permissions, true)) {
            Logger::warning('admin_permission_denied', [
                'role' => $role,
                'permission' => $permission
            ]);
            throw new \RuntimeException("Permission denied: {$permission}");
        }
    }
    
    /**
     * Login admin user.
     * 
     * @param string $username Username
     * @param string $password Password
     * @return array{success: bool, admin?: array, error?: string}
     */
    public static function login(string $username, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check for lockout
        if (self::isLockedOut($ip)) {
            Logger::warning('admin_login_locked_out', ['ip' => $ip, 'username' => $username]);
            return [
                'success' => false,
                'error' => 'Too many failed attempts. Please try again later.'
            ];
        }
        
        try {
            $pdo = Database::ensureConnection();
            
            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, role, is_active 
                FROM admin_users 
                WHERE username = :username
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($admin === false || !password_verify($password, $admin['password_hash'])) {
                self::recordFailedAttempt($ip);
                Logger::warning('admin_login_failed', ['ip' => $ip, 'username' => $username]);
                return [
                    'success' => false,
                    'error' => 'Invalid username or password'
                ];
            }
            
            if (!$admin['is_active']) {
                return [
                    'success' => false,
                    'error' => 'Account is disabled'
                ];
            }
            
            // Clear failed attempts
            self::clearFailedAttempts($ip);
            
            // Start session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION[self::SESSION_KEY] = [
                'admin_id' => (int)$admin['id'],
                'username' => $admin['username'],
                'role' => $admin['role'],
                'created_at' => time(),
                'expires_at' => time() + self::SESSION_EXPIRY,
            ];
            
            // Update last login
            $updateStmt = $pdo->prepare("
                UPDATE admin_users 
                SET last_login_at = NOW(), last_login_ip = :ip 
                WHERE id = :id
            ");
            $updateStmt->execute(['ip' => $ip, 'id' => $admin['id']]);
            
            Logger::info('admin_login_success', [
                'admin_id' => $admin['id'],
                'username' => $admin['username'],
                'ip' => $ip
            ]);
            
            return [
                'success' => true,
                'admin' => [
                    'id' => (int)$admin['id'],
                    'username' => $admin['username'],
                    'role' => $admin['role'],
                ]
            ];
            
        } catch (\PDOException $e) {
            Logger::error('admin_login_error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'An error occurred during login'
            ];
        }
    }
    
    /**
     * Logout admin user.
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION[self::SESSION_KEY]);
        session_destroy();
    }
    
    /**
     * Check if IP is locked out.
     */
    private static function isLockedOut(string $ip): bool
    {
        if (function_exists('apcu_fetch')) {
            $key = "admin_lockout:{$ip}";
            $lockoutUntil = apcu_fetch($key);
            
            if ($lockoutUntil !== false && $lockoutUntil > time()) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Record a failed login attempt.
     */
    private static function recordFailedAttempt(string $ip): void
    {
        if (function_exists('apcu_fetch')) {
            $attemptsKey = "admin_attempts:{$ip}";
            $lockoutKey = "admin_lockout:{$ip}";
            
            $attempts = apcu_fetch($attemptsKey);
            $attempts = $attempts === false ? 1 : $attempts + 1;
            
            apcu_store($attemptsKey, $attempts, self::LOCKOUT_DURATION);
            
            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                apcu_store($lockoutKey, time() + self::LOCKOUT_DURATION, self::LOCKOUT_DURATION);
            }
        }
    }
    
    /**
     * Clear failed login attempts.
     */
    private static function clearFailedAttempts(string $ip): void
    {
        if (function_exists('apcu_delete')) {
            apcu_delete("admin_attempts:{$ip}");
            apcu_delete("admin_lockout:{$ip}");
        }
    }
    
    /**
     * Create admin user (for setup).
     * 
     * @param string $username Username
     * @param string $password Password
     * @param string $role Role (super_admin, admin, moderator, viewer)
     * @return array{success: bool, admin_id?: int, error?: string}
     */
    public static function createAdminUser(string $username, string $password, string $role = 'admin'): array
    {
        if (!array_key_exists($role, self::ROLE_PERMISSIONS)) {
            return ['success' => false, 'error' => 'Invalid role'];
        }
        
        if (strlen($password) < 12) {
            return ['success' => false, 'error' => 'Password must be at least 12 characters'];
        }
        
        try {
            $pdo = Database::ensureConnection();
            
            // Check if username exists
            $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = :username");
            $checkStmt->execute(['username' => $username]);
            
            if ($checkStmt->fetch() !== false) {
                return ['success' => false, 'error' => 'Username already exists'];
            }
            
            // Create user
            $stmt = $pdo->prepare("
                INSERT INTO admin_users (username, password_hash, role, is_active, created_at)
                VALUES (:username, :password_hash, :role, 1, NOW())
            ");
            $stmt->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                'role' => $role,
            ]);
            
            $adminId = (int)$pdo->lastInsertId();
            
            Logger::info('admin_user_created', [
                'admin_id' => $adminId,
                'username' => $username,
                'role' => $role
            ]);
            
            return [
                'success' => true,
                'admin_id' => $adminId
            ];
            
        } catch (\PDOException $e) {
            Logger::error('admin_user_creation_failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to create admin user'];
        }
    }
    
    /**
     * Generate API token for admin user.
     * 
     * @param int $adminId Admin user ID
     * @param string $name Token name/description
     * @param int|null $expiresInDays Days until expiration (null for no expiry)
     * @return array{success: bool, token?: string, error?: string}
     */
    public static function generateApiToken(int $adminId, string $name, ?int $expiresInDays = 365): array
    {
        try {
            $pdo = Database::ensureConnection();
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            
            $expiresAt = $expiresInDays !== null 
                ? date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"))
                : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO admin_api_tokens (admin_id, token_hash, name, expires_at, created_at)
                VALUES (:admin_id, :token_hash, :name, :expires_at, NOW())
            ");
            $stmt->execute([
                'admin_id' => $adminId,
                'token_hash' => $tokenHash,
                'name' => $name,
                'expires_at' => $expiresAt,
            ]);
            
            return [
                'success' => true,
                'token' => $token // Only shown once!
            ];
            
        } catch (\PDOException $e) {
            return ['success' => false, 'error' => 'Failed to generate API token'];
        }
    }
}

