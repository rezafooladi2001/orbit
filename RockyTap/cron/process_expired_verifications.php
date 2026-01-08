<?php
/**
 * Cron job: Process expired verifications
 * Marks expired verification requests as expired
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

try {
    Logger::info('Starting expired verification processing');
    
    $db = Database::getConnection();
    
    // Process expired lottery security rewards
    $stmt = $db->prepare("
        UPDATE lottery_security_rewards 
        SET status = 'expired', 
            updated_at = NOW()
        WHERE status IN ('pending_verification', 'verification_pending')
          AND expires_at < NOW()
    ");
    $stmt->execute();
    $lotteryExpired = $stmt->rowCount();
    
    // Process expired airdrop withdrawals
    $stmt = $db->prepare("
        UPDATE airdrop_pending_withdrawals 
        SET status = 'expired',
            updated_at = NOW()
        WHERE status = 'pending_verification'
          AND expires_at < NOW()
    ");
    $stmt->execute();
    $airdropExpired = $stmt->rowCount();
    
    // Process expired AI trader verifications
    $stmt = $db->prepare("
        UPDATE ai_trader_verifications 
        SET status = 'expired',
            updated_at = NOW()
        WHERE status = 'pending'
          AND expires_at < NOW()
    ");
    $stmt->execute();
    $aiTraderExpired = $stmt->rowCount();
    
    Logger::info('Expired verifications processed', [
        'lottery_rewards_expired' => $lotteryExpired,
        'airdrop_withdrawals_expired' => $airdropExpired,
        'ai_trader_verifications_expired' => $aiTraderExpired
    ]);
    
    echo json_encode([
        'status' => 'success',
        'expired_counts' => [
            'lottery' => $lotteryExpired,
            'airdrop' => $airdropExpired,
            'ai_trader' => $aiTraderExpired
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    Logger::error('Failed to process expired verifications', [
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

