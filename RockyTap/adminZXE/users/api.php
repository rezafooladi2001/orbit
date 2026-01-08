<?php

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Security\AdminAuth;

// Require admin authentication
AdminAuth::requireAdmin();

include '../../bot/config.php';
include '../../bot/functions.php';

$MySQLi = new mysqli('localhost',$DB['username'],$DB['password'],$DB['dbname']);
$MySQLi->query("SET NAMES 'utf8'");
$MySQLi->set_charset('utf8mb4');
if ($MySQLi->connect_error) die;
function ToDie($MySQLi){
$MySQLi->close();
die;
}

$q = $_REQUEST['q'] ?? '';

// Validate and sanitize search query
if (empty($q)) {
    echo json_encode([]);
    $MySQLi->close();
    exit;
}

// If it's a numeric ID, use prepared statement
$userId = filter_var($q, FILTER_VALIDATE_INT);
if ($userId !== false && $userId > 0) {
    $stmt = $MySQLi->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 30");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $get_all = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $get_all = [];
    }
} else {
    // Search by name/username - sanitize for LIKE query
    $searchTerm = '%' . $MySQLi->real_escape_string($q) . '%';
    $stmt = $MySQLi->prepare("SELECT * FROM `users` WHERE `first_name` LIKE ? OR `last_name` LIKE ? OR `username` LIKE ? LIMIT 30");
    if ($stmt) {
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $get_all = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $get_all = [];
    }
}

$MySQLi->close();
echo json_encode($get_all);