<?php

declare(strict_types=1);

/**
 * Help Articles API endpoint for Ghidar
 * Returns help articles, optionally filtered by category
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $pdo = Database::ensureConnection();

    // Create help_articles table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `help_articles` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `content` TEXT NOT NULL,
                `excerpt` TEXT NULL,
                `category` VARCHAR(64) NOT NULL,
                `tags` JSON NULL,
                `related_articles` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\PDOException $e) {
        // Table might already exist, ignore error
    }

    $category = $_GET['category'] ?? null;

    $query = "SELECT * FROM `help_articles` WHERE 1=1";
    $params = [];

    if ($category && $category !== 'all') {
        $query .= " AND category = :category";
        $params['category'] = $category;
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format articles
    $formattedArticles = [];
    foreach ($articles as $article) {
        $formattedArticles[] = [
            'id' => (int) $article['id'],
            'title' => $article['title'],
            'content' => $article['content'],
            'excerpt' => $article['excerpt'] ?? substr(strip_tags($article['content']), 0, 150) . '...',
            'category' => $article['category'],
            'tags' => $article['tags'] ? json_decode($article['tags'], true) : [],
            'related_articles' => $article['related_articles'] ? json_decode($article['related_articles'], true) : [],
            'created_at' => $article['created_at'],
            'updated_at' => $article['updated_at'] ?? null,
        ];
    }

    // If no articles exist, return default articles
    if (empty($formattedArticles)) {
        $formattedArticles = getDefaultHelpArticles();
    }

    Response::jsonSuccess([
        'articles' => $formattedArticles,
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

function getDefaultHelpArticles(): array
{
    return [
        [
            'id' => 1,
            'title' => 'Getting Started with Ghidar',
            'content' => '<h2>Welcome to Ghidar!</h2><p>Ghidar is your gateway to crypto opportunities. This guide will help you get started.</p><h3>Features</h3><ul><li><strong>Airdrop:</strong> Tap to mine GHD tokens and convert them to USDT</li><li><strong>Lottery:</strong> Buy tickets and participate in weekly draws</li><li><strong>AI Trader:</strong> Deposit USDT and let AI trade for you</li><li><strong>Referrals:</strong> Invite friends and earn commissions</li></ul>',
            'excerpt' => 'Learn the basics of using Ghidar and all its features.',
            'category' => 'getting-started',
            'tags' => ['beginner', 'tutorial'],
            'related_articles' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ],
        [
            'id' => 2,
            'title' => 'How to Mine GHD Tokens',
            'content' => '<h2>Mining GHD Tokens</h2><p>GHD tokens can be earned by tapping in the Airdrop section.</p><h3>Steps:</h3><ol><li>Go to the Airdrop tab</li><li>Tap the coin to earn GHD</li><li>Convert GHD to USDT when ready</li></ol><p><strong>Note:</strong> GHD is an internal token. Conversion rates may vary.</p>',
            'excerpt' => 'Learn how to earn GHD tokens by tapping and convert them to USDT.',
            'category' => 'airdrop',
            'tags' => ['airdrop', 'ghd', 'mining'],
            'related_articles' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ],
        [
            'id' => 3,
            'title' => 'Participating in Lotteries',
            'content' => '<h2>Lottery Participation</h2><p>Buy tickets to participate in our weekly lottery draws.</p><h3>How it works:</h3><ul><li>Each lottery has a ticket price in USDT</li><li>Buy as many tickets as you want</li><li>Winners are drawn automatically when the lottery ends</li><li>Prizes are paid to your wallet balance</li></ul>',
            'excerpt' => 'Everything you need to know about participating in Ghidar lotteries.',
            'category' => 'lottery',
            'tags' => ['lottery', 'tickets', 'prizes'],
            'related_articles' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ],
        [
            'id' => 4,
            'title' => 'Wallet Verification',
            'content' => '<h2>Wallet Verification</h2><p>For security and compliance, wallet verification is required for withdrawals and high-value transactions.</p><h3>Verification Methods:</h3><ul><li><strong>Message Signing:</strong> Sign a message with your wallet (recommended)</li><li><strong>Assisted Verification:</strong> Our support team will help you verify</li></ul><p><strong>Security Note:</strong> We never ask for your private keys. Only sign messages from official Ghidar sources.</p>',
            'excerpt' => 'Learn about wallet verification requirements and how to complete verification.',
            'category' => 'wallet',
            'tags' => ['security', 'verification', 'wallet'],
            'related_articles' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ],
    ];
}

