<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Security\AdminAuth;
use Ghidar\Security\CSRFProtection;
use Ghidar\Validation\AdminValidator;

// Require admin authentication
AdminAuth::requireAdmin();

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFProtection::requireToken();
}

include '../../../bot/config.php';
include '../../../bot/functions.php';

$MySQLi = new mysqli('localhost',$DB['username'],$DB['password'],$DB['dbname']);
$MySQLi->query("SET NAMES 'utf8'");
$MySQLi->set_charset('utf8mb4');
if ($MySQLi->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

function ToDie($MySQLi){
    $MySQLi->close();
    die;
}

// Validate and sanitize user_id
try {
    $user_id = AdminValidator::validateUserId($_REQUEST['q'] ?? null);
} catch (\InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    $MySQLi->close();
    exit;
}

$action = $_REQUEST['action'] ?? '';

// Ban user
if($action == 'banUser'){
    $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = 'banned' WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to ban user']);
    }
    $stmt->close();
}

// Unban user
if($action == 'unbanUser'){
    $stmt = $MySQLi->prepare("UPDATE `users` SET `step` = '' WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to unban user']);
    }
    $stmt->close();
}

// Change user score
if($action == 'changeUserScore'){
    try {
        $newScore = AdminValidator::validateScore($_REQUEST['newScore'] ?? null);
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }
    $stmt = $MySQLi->prepare("UPDATE `users` SET `score` = ? WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("ii", $newScore, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update score']);
    }
    $stmt->close();
}

// Change user balance
if($action == 'changeUserBalance'){
    try {
        $newBalance = AdminValidator::validateBalance($_REQUEST['newBalance'] ?? null);
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }
    $stmt = $MySQLi->prepare("UPDATE `users` SET `balance` = ? WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("si", $newBalance, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update balance']);
    }
    $stmt->close();
}

// Reset user tapping guru
if($action == 'resetUserTappingGuru'){
    $resetValue = 3;
    $stmt = $MySQLi->prepare("UPDATE `users` SET `tappingGuruLeft` = ? WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("ii", $resetValue, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to reset tapping guru']);
    }
    $stmt->close();
}

// Reset user full tank
if($action == 'resetUserFullTank'){
    $resetValue = 3;
    $stmt = $MySQLi->prepare("UPDATE `users` SET `fullTankLeft` = ? WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("ii", $resetValue, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to reset full tank']);
    }
    $stmt->close();
}

// Change multitap level
if($action == 'changeMultiTapLevel'){
    try {
        $newLevel = AdminValidator::validateLevel($_REQUEST['newLevel'] ?? null);
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }
    $stmt = $MySQLi->prepare("UPDATE `users` SET `multitap` = ? WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("ii", $newLevel, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update multitap level']);
    }
    $stmt->close();
}

// Change energy limit level
if($action == 'changeEnergyLimitLevel'){
    try {
        $newLevel = AdminValidator::validateLevel($_REQUEST['newLevel'] ?? null);
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }
    $stmt = $MySQLi->prepare("UPDATE `users` SET `energyLimit` = ? WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("ii", $newLevel, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update energy limit level']);
    }
    $stmt->close();
}

// Change recharging speed level
if($action == 'changeRechargingSpeedLevel'){
    try {
        $newLevel = AdminValidator::validateLevel($_REQUEST['newLevel'] ?? null);
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }
    $stmt = $MySQLi->prepare("UPDATE `users` SET `rechargingSpeed` = ? WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    $stmt->bind_param("ii", $newLevel, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update recharging speed level']);
    }
    $stmt->close();
}

// Send message to user
if($action == 'sendMessageToUser'){
    try {
        $text = AdminValidator::validateText($_REQUEST['text'] ?? '');
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }
    LampStack('sendMessage',[
        'chat_id' => $user_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ]);
    echo json_encode(['success' => true]);
}








$MySQLi->close();