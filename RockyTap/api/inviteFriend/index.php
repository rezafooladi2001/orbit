<?php

declare(strict_types=1);

/**
 * Invite Friend API endpoint for Ghidar
 * Sends referral invite link via Telegram bot.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Telegram\BotClient;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 10 requests per 60 seconds (prevent spam)
    if (!RateLimiter::checkAndIncrement($userId, 'invite_friend', 10, 60)) {
        Response::jsonErrorLegacy('rate_limit_exceeded', 'Too many requests', 429);
        exit;
    }

    $pdo = Database::ensureConnection();

    // Get user data to verify user exists
    $stmt = $pdo->prepare('SELECT `id` FROM `users` WHERE `id` = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$get_user) {
        Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
        exit;
    }

    // Build referral link
    $botUsername = Config::get('TELEGRAM_BOT_USERNAME', 'my_bot_name');
    $inviteUrl = 'https://t.me/' . $botUsername . '?start=' . $userId;
    $inviteText = urlencode('🎁 +2.5k Coins as a first-time gift');
    
    $shareUrl = 'https://t.me/share/url?url=' . urlencode($inviteUrl) . '&text=' . str_replace('+', ' ', $inviteText);

    // Send invite message via bot
    $bot = new BotClient();
    $bot->sendMessage($userId, 'Invite friend and get <b>2500</b> coins!
If they have <b>Telegram premium</b> you will get <b>10,000</b> coins', [
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => 'Send Invite To Friends', 'url' => $shareUrl]],
            ]
        ])
    ]);

    Response::jsonSuccessLegacy([
        'message' => 'Invite sent successfully',
        'inviteUrl' => $inviteUrl,
    ]);

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('InviteFriend endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
