<?php

declare(strict_types=1);

/**
 * Generate Compliance Report API endpoint
 * GET /api/compliance/report/:verificationId
 * Generates a compliance report for a specific verification
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Security\EncryptionService;

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get verification ID from URL path
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $reportIndex = array_search('report', $pathParts);
    $verificationId = $reportIndex !== false && isset($pathParts[$reportIndex + 1]) 
        ? $pathParts[$reportIndex + 1] 
        : null;

    if (!$verificationId || !is_numeric($verificationId)) {
        Response::jsonError('MISSING_VERIFICATION_ID', 'Verification ID is required', 400);
        exit;
    }

    $verificationId = (int) $verificationId;

    $db = Database::ensureConnection();

    // Get verification details
    $stmt = $db->prepare(
        'SELECT v.*, u.first_name, u.last_name, u.username, u.language_code
         FROM `wallet_verifications` v
         LEFT JOIN `users` u ON v.user_id = u.id
         WHERE v.id = :verification_id LIMIT 1'
    );
    $stmt->execute(['verification_id' => $verificationId]);
    $verification = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$verification) {
        Response::jsonError('VERIFICATION_NOT_FOUND', 'Verification not found', 404);
        exit;
    }

    // Check authorization (user must own the verification or be admin)
    if ((int) $verification['user_id'] !== $userId) {
        // Check if user is admin
        if (!UserContext::isAdmin($userId)) {
            Response::jsonError('UNAUTHORIZED', 'You do not have access to this report', 403);
            exit;
        }
    }

    // Get audit log entries
    $stmt = $db->prepare(
        'SELECT * FROM `wallet_verification_audit_log`
         WHERE verification_id = :verification_id
         ORDER BY created_at ASC'
    );
    $stmt->execute(['verification_id' => $verificationId]);
    $auditLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get verification attempts
    $stmt = $db->prepare(
        'SELECT * FROM `wallet_verification_attempts`
         WHERE verification_id = :verification_id
         ORDER BY created_at ASC'
    );
    $stmt->execute(['verification_id' => $verificationId]);
    $attempts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get assisted verification data if exists
    $assistedData = null;
    if ($verification['verification_method'] === 'assisted') {
        $stmt = $db->prepare(
            'SELECT id, data_type, status, reviewed_by, reviewed_at, created_at
             FROM `assisted_verification_data`
             WHERE verification_id = :verification_id'
        );
        $stmt->execute(['verification_id' => $verificationId]);
        $assistedData = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Decode risk factors
    $riskFactors = [];
    if ($verification['risk_factors']) {
        $riskFactors = json_decode($verification['risk_factors'], true) ?? [];
    }

    // Build compliance report
    $report = [
        'verification_id' => $verification['id'],
        'report_generated_at' => date('Y-m-d H:i:s'),
        'verification_details' => [
            'user_id' => $verification['user_id'],
            'user_name' => trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? '')),
            'username' => $verification['username'],
            'feature' => $verification['feature'],
            'verification_method' => $verification['verification_method'],
            'wallet_address' => $verification['wallet_address'],
            'wallet_network' => $verification['wallet_network'],
            'status' => $verification['status'],
            'risk_score' => (int) $verification['risk_score'],
            'risk_level' => $verification['risk_level'],
            'risk_factors' => $riskFactors,
            'created_at' => $verification['created_at'],
            'expires_at' => $verification['expires_at'],
            'verified_at' => $verification['verified_at'],
            'rejection_reason' => $verification['rejection_reason']
        ],
        'security_metadata' => [
            'ip_address' => $verification['ip_address'],
            'verification_ip' => $verification['verification_ip']
        ],
        'audit_trail' => array_map(function($log) {
            return [
                'action_type' => $log['action_type'],
                'action_details' => json_decode($log['action_details'], true) ?? [],
                'ip_address' => $log['ip_address'],
                'user_agent' => $log['user_agent'],
                'timestamp' => $log['created_at']
            ];
        }, $auditLogs),
        'verification_attempts' => array_map(function($attempt) {
            return [
                'ip_address' => $attempt['ip_address'],
                'wallet_address' => $attempt['wallet_address'],
                'wallet_network' => $attempt['wallet_network'],
                'success' => (bool) $attempt['success'],
                'created_at' => $attempt['created_at'],
                'completed_at' => $attempt['completed_at']
            ];
        }, $attempts),
        'assisted_verification_data' => $assistedData ? array_map(function($data) {
            return [
                'data_type' => $data['data_type'],
                'status' => $data['status'],
                'reviewed_at' => $data['reviewed_at'],
                'created_at' => $data['created_at']
            ];
        }, $assistedData) : null,
        'compliance_flags' => self::generateComplianceFlags($verification, $auditLogs, $attempts)
    ];

    Response::jsonSuccess($report);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while generating compliance report', 500);
}

/**
 * Generate compliance flags based on verification data.
 */
function generateComplianceFlags(array $verification, array $auditLogs, array $attempts): array
{
    $flags = [];

    // High risk flag
    if ($verification['risk_level'] === 'high') {
        $flags[] = [
            'type' => 'high_risk',
            'severity' => 'high',
            'message' => 'Verification flagged as high risk',
            'details' => json_decode($verification['risk_factors'], true) ?? []
        ];
    }

    // Multiple failed attempts
    $failedAttempts = array_filter($attempts, function($attempt) {
        return !$attempt['success'];
    });
    if (count($failedAttempts) > 3) {
        $flags[] = [
            'type' => 'multiple_failed_attempts',
            'severity' => 'medium',
            'message' => 'Multiple failed verification attempts detected',
            'count' => count($failedAttempts)
        ];
    }

    // IP address changes
    $uniqueIPs = array_unique(array_column($attempts, 'ip_address'));
    if (count($uniqueIPs) > 3) {
        $flags[] = [
            'type' => 'multiple_ip_addresses',
            'severity' => 'medium',
            'message' => 'Verification attempts from multiple IP addresses',
            'ip_count' => count($uniqueIPs)
        ];
    }

    // Admin override flag
    if ($verification['admin_override_by']) {
        $flags[] = [
            'type' => 'admin_override',
            'severity' => 'info',
            'message' => 'Verification manually approved by admin',
            'admin_id' => $verification['admin_override_by'],
            'reason' => $verification['admin_override_reason']
        ];
    }

    return $flags;
}

