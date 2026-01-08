<?php
/**
 * Telegram Bot Webhook Handler for Ghidar
 * Handles incoming Telegram updates and bot commands.
 */

// Disable ExceptionHandler for bot - we handle errors ourselves and return 200 OK to Telegram
$originalExceptionHandler = null;
$originalErrorHandler = null;

require_once __DIR__ . '/../../bootstrap.php';

// Override ExceptionHandler for bot - Telegram expects 200 OK even on errors
set_exception_handler(function($exception) {
    error_log("[BOT] Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    http_response_code(200);
    die;
});

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log("[BOT] Error: $message in $file:$line");
    return false; // Let PHP handle it
});

// Config is already loaded in bootstrap.php, no need to use it again
use Ghidar\Telegram\BotClient;
use Ghidar\Referral\ReferralService;
use Ghidar\Core\Database;

include './config.php';
include './functions.php';

// Initialize Telegram Bot Client
$bot = new BotClient();

// Database connection using PDO
$pdo = Database::getConnection();

// Legacy MySQLi connection for admin functions (using PDO credentials)
// Note: MySQLi doesn't support SSL options like PDO, so for TiDB Cloud we'll use PDO instead
// MySQLi is only used for legacy admin functions, so we'll skip it if SSL is required
$MySQLi = null;
$dbHost = \Ghidar\Config\Config::get('DB_HOST', 'localhost');
// Only try MySQLi for local connections (TiDB Cloud requires SSL which MySQLi doesn't support well)
if (strpos($dbHost, 'localhost') === 0 || strpos($dbHost, '127.0.0.1') === 0) {
    try {
        $MySQLi = mysqli_connect(
            $dbHost,
            \Ghidar\Config\Config::get('DB_USERNAME'),
            \Ghidar\Config\Config::get('DB_PASSWORD'),
            \Ghidar\Config\Config::get('DB_DATABASE'),
            \Ghidar\Config\Config::getInt('DB_PORT', 3306)
        );
        if ($MySQLi) {
            mysqli_set_charset($MySQLi, 'utf8mb4');
        }
    } catch (Exception $e) {
        // MySQLi connection failed - admin functions will use PDO instead
        error_log("[BOT] MySQLi connection skipped (SSL required for remote DB)");
    }
} else {
    // For remote databases (TiDB Cloud), MySQLi is not available
    // Admin functions that need MySQLi will need to be migrated to PDO
    error_log("[BOT] MySQLi connection skipped for remote database (TiDB Cloud requires SSL)");
}


$update = json_decode(file_get_contents('php://input'));

// Initialize variables
$msg = null;
$chat_id = null;
$from_id = null;
$first_name = null;
$last_name = null;
$username = null;
$is_premium = false;
$language_code = 'en';
$chat_type = null;
$message_id = null;
$reply_message_id = null;

if(isset($update->message)) {
    $msg = $update->message->text ?? null;
    $chat_id = $update->message->chat->id ?? null;
    $from_id = $update->message->from->id ?? null;
    $first_name = $update->message->from->first_name ?? null;
    $last_name = $update->message->from->last_name ?? null;
    $username = $update->message->from->username ?? null;
    $is_premium = $update->message->from->is_premium ?? false;
    $language_code = $update->message->from->language_code ?? 'en';
    $chat_type = $update->message->chat->type ?? null;
    $message_id = $update->message->message_id ?? null;
    $reply_message_id = $update->message->reply_to_message->message_id ?? null;
}

// Only process private chat messages
// Note: $msg can be null for non-text messages (photos, stickers, etc.), so we allow null
if ($chat_type !== 'private' || !$from_id) {
    http_response_code(200);
    die;
}

// Handle /start with referral payload (e.g., /start ref_123)
if ($msg && explode(' ', $msg)[0] === '/start' && isset(explode(' ', $msg)[1])) {
    $payload = explode(' ', $msg)[1];
    
    // Check if payload is a referral code (ref_123 format)
    if (strpos($payload, 'ref_') === 0) {
        $referrerIdStr = substr($payload, 4);
        
        if (is_numeric($referrerIdStr)) {
            $referrerId = (int) $referrerIdStr;
            
            // Ensure user exists first (create if needed)
            $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
            $stmt->execute(['id' => $from_id]);
            $UserDataBase = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$UserDataBase) {
                $time = time();
                $stmt = $pdo->prepare('INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `language_code`, `joining_date`, `is_premium`) VALUES (:id, :first_name, :last_name, :username, :language_code, :joining_date, :is_premium)');
                $stmt->execute([
                    'id' => $from_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $username,
                    'language_code' => $language_code,
                    'joining_date' => $time,
                    'is_premium' => $is_premium ? 1 : 0  // Convert boolean to integer (0 or 1)
                ]);
            }
            
            // Try to attach referrer using ReferralService
            try {
                ReferralService::attachReferrerIfEmpty($from_id, $referrerId);
                
                // Legacy: Update referrer's balance and referral count (keep for backward compatibility)
                $balance = 2500;
                if($is_premium) $balance = 10000;
                $stmt = $pdo->prepare('UPDATE `users` SET `balance` = `balance` + :balance, `totalReferralsRewards` = `totalReferralsRewards` + :balance2, `referrals` = `referrals` + 1 WHERE `id` = :referrer_id AND EXISTS (SELECT 1 FROM `users` WHERE `id` = :from_id AND `inviter_id` = :referrer_id2) LIMIT 1');
                $stmt->execute([
                    'balance' => $balance,
                    'balance2' => $balance,
                    'referrer_id' => $referrerId,
                    'from_id' => $from_id,
                    'referrer_id2' => $referrerId
                ]);
                
                // Notify referrer
                $invited_name = str_replace(['<', '>', '&'], ['&lt;', '&gt;', '&amp;'], $first_name);
                $bot->sendMessage($referrerId, "congratulations 🌱\n<b>$invited_name</b> joined the bot by your link", [
                    'parse_mode' => 'HTML',
                ]);
            } catch (\InvalidArgumentException $e) {
                // Invalid referrer or self-referral - silently continue
            } catch (\Exception $e) {
                // Log error but continue
                error_log("ReferralService error in bot: " . $e->getMessage());
            }
            
            $bot->sendPhoto($from_id, new CURLFILE('main.png'), [
                'caption' => '
<b>💎 Welcome to Ghidar!</b>

Your gateway to crypto opportunities:

🎟️ <b>Lottery</b> - Buy tickets and win big prizes
⛏️ <b>Airdrop</b> - Mine GHD tokens daily
📈 <b>AI Trader</b> - Let AI trade for you

You joined through a referral link - both you and your friend earned bonus coins!

Invite more friends to earn even more rewards.
',
                'parse_mode' => 'HTML',
                'reply_to_message_id' => $message_id,
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => '💎 Open Ghidar', 'web_app' => ['url' => $web_app]]],
                    ]
                ])
            ]);
            
            die;
        }
    }
}




$stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
$stmt->execute(['id' => $from_id]);
$UserDataBase = $stmt->fetch(\PDO::FETCH_ASSOC);
$isNewUser = false;
if (!$UserDataBase) {
    $isNewUser = true;
    $time = time();
    $stmt = $pdo->prepare('INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `language_code`, `joining_date`, `is_premium`) VALUES (:id, :first_name, :last_name, :username, :language_code, :joining_date, :is_premium)');
    $stmt->execute([
        'id' => $from_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'username' => $username,
        'language_code' => $language_code,
        'joining_date' => $time,
        'is_premium' => $is_premium ? 1 : 0  // Convert boolean to integer (0 or 1)
    ]);
    
    // Send enhanced welcome notification for new users
    try {
        \Ghidar\Notifications\NotificationService::notifyWelcomeNewUser(
            $from_id,
            $first_name ?? 'Friend',
            $is_premium
        );
    } catch (\Throwable $e) {
        error_log("[BOT] Failed to send welcome notification: " . $e->getMessage());
    }
}


if (isset($UserDataBase) && isset($UserDataBase['step']) && $UserDataBase['step'] == 'banned') {
    $bot->sendMessage($from_id, '<b>You Are Banned From The Bot.</b>', [
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode([
            'KeyboardRemove' => [],
            'remove_keyboard' => true
        ])
    ]);
    die;
}

// API Debug command - minimal API test
if ($msg && $msg === '/apidebug') {
    $apidebug_url = $base_url . '/RockyTap/ghidar/api-debug.html';
    error_log("[BOT] /apidebug command received from user $from_id");
    $bot->sendMessage($from_id, '
🔧 <b>API Debug</b>

Minimal API test page. Shows exactly what happens when calling /health/ and /me/.
', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '🔧 API Debug', 'web_app' => ['url' => $apidebug_url]]],
            ]
        ])
    ]);
    die;
}

// Test API command - tests if API calls work
if ($msg && $msg === '/test') {
    $test_url = $base_url . '/RockyTap/ghidar/test-api.html';
    error_log("[BOT] /test command received from user $from_id");
    $bot->sendMessage($from_id, '
🧪 <b>API Test</b>

Tap the button below to test if the API works correctly.
This will verify that authentication is working.
', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '🧪 Test API', 'web_app' => ['url' => $test_url]]],
            ]
        ])
    ]);
    die;
}

// Debug command - opens debug page to check SDK status
if ($msg && $msg === '/debug') {
    $debug_url = $base_url . '/RockyTap/ghidar/debug.html';
    error_log("[BOT] /debug command received from user $from_id");
    $bot->sendMessage($from_id, '
🔍 <b>Debug Mode</b>

Tap the button below to open the debug page.
This will show you the Telegram SDK status and help diagnose any issues.

<b>What to look for:</b>
✅ initData PRESENT = Working correctly
❌ initData EMPTY = BotFather not configured
', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '🔍 Open Debug Page', 'web_app' => ['url' => $debug_url]]],
            ]
        ])
    ]);
    die;
}

if ($msg && $msg === '/start') {
    // Debug logging
    error_log("[BOT] /start command received from user $from_id");
    error_log("[BOT] web_app URL: " . ($web_app ?? 'NOT SET'));
    error_log("[BOT] main.png path: " . __DIR__ . '/main.png');
    error_log("[BOT] main.png exists: " . (file_exists(__DIR__ . '/main.png') ? 'YES' : 'NO'));
    
    $photo_path = __DIR__ . '/main.png';
    if (!file_exists($photo_path)) {
        error_log("[BOT] ERROR: main.png not found at $photo_path");
        // Fallback: send text message instead
        $bot->sendMessage($from_id, '
<b>💎 Welcome to Ghidar!</b>

Your secure gateway to crypto opportunities:

🎟️ <b>Lottery</b> - Buy tickets and win big prizes
⛏️ <b>Airdrop</b> - Mine GHD tokens daily
📈 <b>AI Trader</b> - Let AI trade for you

🛡️ <b>Secure & Trusted</b>
⚡ Powered by Telegram - Your data is protected by Telegram\'s secure authentication system

Start earning now - tap the button below to open the app!
', 'HTML', false, false, $message_id, json_encode([
            'inline_keyboard' => [
                [['text' => '💎 Open Ghidar', 'web_app' => ['url' => $web_app]]],
            ]
        ]));
    } else {
        $result = $bot->sendPhoto($from_id, new CURLFILE($photo_path), [
            'caption' => '
<b>💎 Welcome to Ghidar!</b>

Your secure gateway to crypto opportunities:

🎟️ <b>Lottery</b> - Buy tickets and win big prizes
⛏️ <b>Airdrop</b> - Mine GHD tokens daily
📈 <b>AI Trader</b> - Let AI trade for you

🛡️ <b>Secure & Trusted</b>
⚡ Powered by Telegram - Your data is protected by Telegram\'s secure authentication system

Start earning now - tap the button below to open the app!
',
            'parse_mode' => 'HTML',
            'reply_to_message_id' => $message_id,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '💎 Open Ghidar', 'web_app' => ['url' => $web_app]]],
                ]
            ])
        ]);
        
        if (!$result) {
            error_log("[BOT] ERROR: sendPhoto failed. Response: " . json_encode($bot->lastResponse ?? 'no response'));
        } else {
            error_log("[BOT] SUCCESS: Photo sent successfully");
        }
    }
    die;
}

// /help command
if ($msg && $msg === '/help') {
    $bot->sendMessage($from_id, '
<b>💎 Ghidar Help</b>

<b>Available Commands:</b>
/start - Start the bot and open the app
/help - Show this help message
/referral - Get your referral link and stats

<b>Features:</b>
🎟️ <b>Lottery</b> - Purchase tickets and participate in weekly draws
⛏️ <b>Airdrop</b> - Tap to mine GHD tokens and convert to USDT
📈 <b>AI Trader</b> - Deposit USDT and let our AI trade for you
👥 <b>Referrals</b> - Invite friends and earn USDT commissions

<b>Need Support?</b>
Contact our support team through the app.
', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '💎 Open Ghidar', 'web_app' => ['url' => $web_app]]],
            ]
        ])
    ]);
    die;
}

// /referral command
if ($msg && $msg === '/referral') {
    try {
        $referralInfo = ReferralService::getReferralInfo($from_id);
        
        $stats = $referralInfo['stats'];
        $message = "
<b>👥 Your Referral Program</b>

🔗 <b>Your Referral Link:</b>
<code>{$referralInfo['referral_link']}</code>

📊 <b>Statistics:</b>
👤 Direct Referrals: {$stats['direct_referrals']}
👥 Indirect Referrals: {$stats['indirect_referrals']}
💰 Total Rewards: \${$stats['total_rewards_usdt']} USDT

💡 Share your link to earn commissions when your referrals make deposits!
";
        
        $bot->sendMessage($from_id, $message, [
            'parse_mode' => 'HTML',
            'reply_to_message_id' => $message_id,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '💎 Open Ghidar', 'web_app' => ['url' => $web_app]]],
                ]
            ])
        ]);
    } catch (\Exception $e) {
        $bot->sendMessage($from_id, '<b>Error loading referral information. Please try again later.</b>', [
            'parse_mode' => 'HTML',
            'reply_to_message_id' => $message_id,
        ]);
        error_log("ReferralService error in /referral: " . $e->getMessage());
    }
    
    die;
}

if ($msg && $msg === 'Back To User Mode ↪️') {
    $stmt = $pdo->prepare('UPDATE `users` SET `step` = null WHERE `id` = :id LIMIT 1');
    $stmt->execute(['id' => $from_id]);
    $tempMsg = $bot->sendMessage($from_id, '<b>...</b>', [
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode([
            'KeyboardRemove' => [],
            'remove_keyboard' => true
        ])
    ]);
    if ($tempMsg && isset($tempMsg->result->message_id)) {
        $bot->deleteMessage($from_id, $tempMsg->result->message_id);
    }
    $bot->sendPhoto($from_id, new CURLFILE('main.png'), [
        'caption' => '
Hey! Welcome to Ghidar!
Tap on the coin and see your balance rise.

Ghidar is a Decentralized Exchange on the TON Blockchain. The biggest part of Ghidar Token GHD distribution will occur among the players here.

Got friends, relatives, co-workers?
Bring them all into the game.
More buddies, more coins.

',
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => 'Play Now', 'web_app' => ['url' => $web_app]]],
            ]
        ])
    ]);
    die;
}






// Admin section

if (!in_array($from_id, $admins_user_id)) {
    die;
}


$panel_menu = json_encode([
'resize_keyboard' => true,
'keyboard' => [
[['text' => 'Statistics', 'web_app' => ['url' => $web_app . '/adminZXE/statics/']]],
[['text' => 'Task Managment', 'web_app' => ['url' => $web_app . '/adminZXE/missions/']], ['text' => 'User Managment', 'web_app' => ['url' => $web_app . '/adminZXE/users/']]],
[['text' => 'BackUP']],
[['text' => 'Send Message'],['text' => 'Forward Message']],
[['text' => 'Turn On Maintenance'],['text' => 'Turn Off Maintenance']],
[['text' => 'Back To User Mode ↪️']],
]
]);



// Admin panel
if ($msg && ($msg === '/admin' || $msg === '🔙')) {
    $stmt = $pdo->prepare('UPDATE `users` SET `step` = null WHERE `id` = :id LIMIT 1');
    $stmt->execute(['id' => $from_id]);
    
    // Gather statistics
    $stats = [];
    
    // Total users
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM `users`');
    $stats['total_users'] = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Users joined today
    $today_start = strtotime('today');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM `users` WHERE `joining_date` >= :today_start');
    $stmt->execute(['today_start' => $today_start]);
    $stats['users_today'] = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total USDT in wallets
    $stmt = $pdo->query('SELECT SUM(usdt_balance) as total FROM `wallets`');
    $stats['total_usdt'] = number_format((float)($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0), 2);
    
    // Total GHD in wallets
    $stmt = $pdo->query('SELECT SUM(ghd_balance) as total FROM `wallets`');
    $stats['total_ghd'] = number_format((float)($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0), 0);
    
    // Active AI Trader balance
    $stmt = $pdo->query('SELECT SUM(current_balance_usdt) as total FROM `ai_accounts`');
    $stats['ai_trader_balance'] = number_format((float)($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0), 2);
    
    // Active lotteries
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM `lotteries` WHERE `status` = :status');
    $stmt->execute(['status' => 'active']);
    $stats['active_lotteries'] = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $statsMessage = "
<b>📊 Ghidar Admin Dashboard</b>

<b>Users:</b>
👥 Total: {$stats['total_users']}
📅 Today: {$stats['users_today']}

<b>Balances:</b>
💵 Total USDT: \${$stats['total_usdt']}
🪙 Total GHD: {$stats['total_ghd']}

<b>AI Trader:</b>
📈 Active Balance: \${$stats['ai_trader_balance']}

<b>Lottery:</b>
🎟️ Active: {$stats['active_lotteries']}
";
    
    $bot->sendMessage($from_id, $statsMessage, [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => $panel_menu
    ]);
    die;
}


// Backup database
if ($msg && $msg === 'BackUP') {
    $sendMessage = $bot->sendMessage($from_id, '⏳', [
        'reply_to_message_id' => $message_id,
    ]);
    dbBackup('localhost', $DB['username'], $DB['password'], $DB['dbname'], 'SQLbackUp');
    $filesize = filesize('SQLbackUp.sql');
    if ($sendMessage && isset($sendMessage->result->message_id)) {
        $bot->deleteMessage($from_id, $sendMessage->result->message_id);
    }
    if (round($filesize / 1024 / 1024) > 19) {
        $bot->sendMessage($from_id, '<b>The size of the bot database is more than 20 MB and I cant send it to you

Please take a backup of the database manually through the host.</b>', [
            'reply_to_message_id' => $message_id,
        ]);
    } else {
        $bot->sendDocument($from_id, new curlFile('SQLbackUp.sql'), [
            'caption' => "<b>The bot database backup was created successfully ✅</b>",
            'reply_to_message_id' => $message_id,
            'parse_mode' => "HTML",
        ]);
    }
    unlink('SQLbackUp.sql');

    die;
}


// Send Message To All
if ($msg && $msg === 'Send Message') {
    if ($MySQLi) {
        $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = 'SendToAll' WHERE `id` = ? LIMIT 1");
        $stmt->bind_param("i", $from_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `step` = 'SendToAll' WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $from_id]);
    }
    $bot->sendMessage($from_id, '<b>Send a message to be sent to all users of the bot :</b>', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [['text' => '🔙']],
            ]
        ])
    ]);
    if ($MySQLi) $MySQLi->close();
    die;
}

if (isset($update->message) && isset($UserDataBase) && isset($UserDataBase['step']) && $UserDataBase['step'] === 'SendToAll') {
    if ($MySQLi) {
        $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = null WHERE `id` = ? LIMIT 1");
        $stmt->bind_param("i", $from_id);
        $stmt->execute();
        $stmt->close();
        
        $MySQLi->query("DELETE FROM `sending` WHERE `type` = 'send' OR `type` = 'forward'");
        
        $stmt = $MySQLi->prepare("INSERT INTO `sending` (`type`,`chat_id`,`msg_id`,`count`) VALUES ('send',?,?,0)");
        $stmt->bind_param("ii", $from_id, $message_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `step` = null WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $from_id]);
        $stmt = $pdo->prepare("DELETE FROM `sending` WHERE `type` = 'send' OR `type` = 'forward'");
        $stmt->execute();
        $stmt = $pdo->prepare("INSERT INTO `sending` (`type`,`chat_id`,`msg_id`,`count`) VALUES ('send',:from_id,:message_id,0)");
        $stmt->execute(['from_id' => $from_id, 'message_id' => $message_id]);
    }
    $bot->sendMessage($from_id, '<b>Public sending operation has started.✅</b>

<u>Please send|forward  any message until the end of the operation❗️</u>', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => $panel_menu
    ]);
    if ($MySQLi) $MySQLi->close();
    die;
}


// Forward Message To All
if ($msg && $msg === 'Forward Message') {
    if ($MySQLi) {
        $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = 'ForToAll' WHERE `id` = ? LIMIT 1");
        $stmt->bind_param("i", $from_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `step` = 'ForToAll' WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $from_id]);
    }
    $bot->sendMessage($from_id, '<b>Forward a message to be forward to all users of the bot :</b>', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [['text' => '🔙']],
            ]
        ])
    ]);
    if ($MySQLi) $MySQLi->close();
    die;
}

if (isset($update->message) && isset($UserDataBase) && isset($UserDataBase['step']) && $UserDataBase['step'] === 'ForToAll') {
    if ($MySQLi) {
        $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = null WHERE `id` = ? LIMIT 1");
        $stmt->bind_param("i", $from_id);
        $stmt->execute();
        $stmt->close();
        
        $MySQLi->query("DELETE FROM `sending` WHERE `type` = 'send' OR `type` = 'forward'");
        
        $stmt = $MySQLi->prepare("INSERT INTO `sending` (`type`,`chat_id`,`msg_id`,`count`) VALUES ('forward',?,?,0)");
        $stmt->bind_param("ii", $from_id, $message_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `step` = null WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $from_id]);
        $stmt = $pdo->prepare("DELETE FROM `sending` WHERE `type` = 'send' OR `type` = 'forward'");
        $stmt->execute();
        $stmt = $pdo->prepare("INSERT INTO `sending` (`type`,`chat_id`,`msg_id`,`count`) VALUES ('forward',:from_id,:message_id,0)");
        $stmt->execute(['from_id' => $from_id, 'message_id' => $message_id]);
    }
    $bot->sendMessage($from_id, '<b>Public forwarding operation has started.✅</b>

<u>Please send|forward  any message until the end of the operation❗️</u>', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => $panel_menu
    ]);
    if ($MySQLi) $MySQLi->close();
    die;
}


// Turn On Maintenance
if ($msg && $msg === 'Turn On Maintenance') {
    if ($MySQLi) {
        $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = 'GetMaintenanceTime' WHERE `id` = ? LIMIT 1");
        $stmt->bind_param("i", $from_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `step` = 'GetMaintenanceTime' WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $from_id]);
    }
    $bot->sendMessage($from_id, '<b>Please give me a time to be on maintenance mode in minute :</b>', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => json_encode([
            'resize_keyboard' => true,
            'keyboard' => [
                [['text' => '🔙']],
            ]
        ])
    ]);
    if ($MySQLi) $MySQLi->close();
    die;
}

if ($msg && is_numeric($msg) && isset($UserDataBase) && isset($UserDataBase['step']) && $UserDataBase['step'] === 'GetMaintenanceTime') {
    if ($MySQLi) {
        $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = '' WHERE `id` = ? LIMIT 1");
        $stmt->bind_param("i", $from_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `step` = '' WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $from_id]);
    }
    $time = round((microtime(true) * 1000) + ($msg * 60 * 1000));
    file_put_contents('.maintenance.txt', $time);
    $bot->sendMessage($from_id, '<b>Maintenance mode activated ✅</b>', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => $panel_menu
    ]);
    if ($MySQLi) $MySQLi->close();
    die;
}

// Turn Off Maintenance
if ($msg && $msg === 'Turn Off Maintenance') {
    unlink('.maintenance.txt');
    $bot->sendMessage($from_id, '<b>Maintenance mode deactivated ✅</b>', [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => $panel_menu
    ]);
    if ($MySQLi) $MySQLi->close();
    die;
}


// /broadcast command - Quick text broadcast to limited users
// Usage: /broadcast Your message here...
if ($msg && strpos($msg, '/broadcast ') === 0) {
    $broadcastText = trim(substr($msg, 11));
    
    if (empty($broadcastText)) {
        $bot->sendMessage($from_id, '<b>Usage:</b> /broadcast Your message here...', [
            'parse_mode' => 'HTML',
            'reply_to_message_id' => $message_id,
        ]);
        if ($MySQLi) $MySQLi->close();
        die;
    }
    
    // For MVP safety, limit to first 100 users
    // TODO: Implement proper queue-based broadcast for production
    $broadcastLimit = 100;
    
    if ($MySQLi) {
        $result = mysqli_query($MySQLi, "SELECT id FROM users LIMIT {$broadcastLimit}");
        $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT id FROM users LIMIT {$broadcastLimit}");
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    $sent = 0;
    $failed = 0;
    
    $bot->sendMessage($from_id, "⏳ Starting broadcast to up to {$broadcastLimit} users...", [
        'reply_to_message_id' => $message_id,
    ]);
    
    foreach ($users as $user) {
        $userId = $user['id'];
        try {
            $res = $bot->sendMessage($userId, $broadcastText, [
                'parse_mode' => 'HTML',
            ]);
            if ($res && isset($res->ok) && $res->ok) {
                $sent++;
            } else {
                $failed++;
            }
            usleep(50000); // 50ms delay to avoid rate limiting
        } catch (Exception $e) {
            $failed++;
        }
    }
    
    $bot->sendMessage($from_id, "
<b>📢 Broadcast Complete</b>

✅ Sent: {$sent}
❌ Failed: {$failed}
📊 Total: " . ($sent + $failed) . "

<i>Note: Broadcasts are limited to {$broadcastLimit} users in MVP mode.</i>
", [
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message_id,
        'reply_markup' => $panel_menu
    ]);
    
    $MySQLi->close();
    die;
}







$MySQLi->close();
die;