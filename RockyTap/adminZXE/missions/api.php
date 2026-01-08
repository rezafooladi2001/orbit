<?php

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Security\AdminAuth;
use Ghidar\Security\CSRFProtection;
use Ghidar\Validation\AdminValidator;

// Require admin authentication
AdminAuth::requireAdmin();

// Cache php://input for JSON requests (can only be read once)
$cachedJsonInput = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && 
    strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $cachedJsonInput = file_get_contents('php://input');
    // Make it available to CSRFProtection via global
    $GLOBALS['_CSRF_JSON_INPUT'] = $cachedJsonInput;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFProtection::requireToken();
}

include '../../bot/config.php';
include '../../bot/functions.php';

$MySQLi = new mysqli('localhost',$DB['username'],$DB['password'],$DB['dbname']);
$MySQLi->query("SET NAMES 'utf8'");
$MySQLi->set_charset('utf8mb4');
if ($MySQLi->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

function ToDie($MySQLi){
    $MySQLi->close();
    die;
}

// Validate action parameter
$action = $_GET['action'] ?? null;
$allowedActions = ['getMissions', 'addMission', 'removeMission', 'addTask', 'removeTask'];
if ($action === null || !in_array($action, $allowedActions, true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid or missing action parameter']);
    $MySQLi->close();
    exit;
}

if ($action == 'getMissions') {
    $missions = [];
    $sql = "SELECT * FROM missions ORDER BY id DESC";
    $result = $MySQLi->query($sql);

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $mission_id = (int)$row['id'];
            $tasks = [];
            $taskStmt = $MySQLi->prepare("SELECT * FROM tasks WHERE mission_id = ? ORDER BY id DESC");
            if ($taskStmt) {
                $taskStmt->bind_param("i", $mission_id);
                $taskStmt->execute();
                $taskResult = $taskStmt->get_result();

                if ($taskResult && $taskResult->num_rows > 0) {
                    while($taskRow = $taskResult->fetch_assoc()) {
                        $tasks[] = $taskRow;
                    }
                }
                $taskStmt->close();
            }

            $row['tasks'] = $tasks;
            $missions[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['missions' => $missions]);
} elseif ($action == 'addMission') {
    $rawInput = $cachedJsonInput ?? file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
        $MySQLi->close();
        exit;
    }
    
    // Validate and sanitize input
    try {
        $name = AdminValidator::validateText($data['name'] ?? '', 255);
        $description = AdminValidator::validateText($data['description'] ?? '', 1000);
        $reward = filter_var($data['reward'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 999999999]
        ]);
        
        if ($reward === false) {
            throw new \InvalidArgumentException('Reward must be a non-negative integer');
        }
    } catch (\InvalidArgumentException $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }

    $stmt = $MySQLi->prepare("INSERT INTO missions (name, reward, description) VALUES (?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    
    $stmt->bind_param("sis", $name, $reward, $description);

    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => $MySQLi->insert_id]);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to insert mission: ' . $stmt->error]);
    }

    $stmt->close();
} elseif ($action == 'removeMission') {
    // Read ID from POST body (JSON) or fallback to GET for backward compatibility
    $rawInput = $cachedJsonInput ?? file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $id = $data['id'] ?? $_GET['id'] ?? null;
    
    try {
        $id = AdminValidator::validateMissionId($id);
    } catch (\InvalidArgumentException $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }

    // Start transaction to ensure atomicity
    $MySQLi->begin_transaction();

    try {
        // Delete related tasks
        $taskStmt = $MySQLi->prepare("DELETE FROM `tasks` WHERE `mission_id` = ?");
        if (!$taskStmt) {
            throw new \Exception('Failed to prepare task deletion statement: ' . $MySQLi->error);
        }
        $taskStmt->bind_param("i", $id);
        if (!$taskStmt->execute()) {
            throw new \Exception('Failed to delete related tasks: ' . $taskStmt->error);
        }
        $taskStmt->close();

        // Delete related user missions
        $userMissionStmt = $MySQLi->prepare("DELETE FROM `user_missions` WHERE `mission_id` = ?");
        if (!$userMissionStmt) {
            throw new \Exception('Failed to prepare user_missions deletion statement: ' . $MySQLi->error);
        }
        $userMissionStmt->bind_param("i", $id);
        if (!$userMissionStmt->execute()) {
            throw new \Exception('Failed to delete related user missions: ' . $userMissionStmt->error);
        }
        $userMissionStmt->close();

        // Delete mission
        $stmt = $MySQLi->prepare("DELETE FROM missions WHERE id = ?");
        if (!$stmt) {
            throw new \Exception('Failed to prepare mission deletion statement: ' . $MySQLi->error);
        }
        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new \Exception('Failed to delete mission: ' . $stmt->error);
        }
        $stmt->close();

        // Commit transaction if all deletions succeeded
        $MySQLi->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        // Rollback transaction on any error
        $MySQLi->rollback();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($action == 'addTask') {
    $rawInput = $cachedJsonInput ?? file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
        $MySQLi->close();
        exit;
    }
    
    // Validate and sanitize input
    try {
        $missionId = AdminValidator::validateMissionId($data['missionId'] ?? null);
        $name = AdminValidator::validateText($data['name'] ?? '', 255);
        $chatId = AdminValidator::validateText($data['chatId'] ?? '', 100);
        $url = AdminValidator::validateText($data['url'] ?? '', 500);
        $type = filter_var($data['type'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 1]
        ]);
        
        if ($type === false) {
            throw new \InvalidArgumentException('Type must be 0 (URL) or 1 (Join Chat)');
        }
    } catch (\InvalidArgumentException $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }

    $stmt = $MySQLi->prepare("INSERT INTO tasks (mission_id, name, chatId, url, type) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $MySQLi->error]);
        $MySQLi->close();
        exit;
    }
    
    $stmt->bind_param("isssi", $missionId, $name, $chatId, $url, $type);

    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => $MySQLi->insert_id]);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to insert task: ' . $stmt->error]);
    }

    $stmt->close();
} elseif ($action == 'removeTask') {
    // Read ID from POST body (JSON) or fallback to GET for backward compatibility
    $rawInput = $cachedJsonInput ?? file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $id = $data['id'] ?? $_GET['id'] ?? null;
    
    try {
        $id = AdminValidator::validateTaskId($id);
    } catch (\InvalidArgumentException $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        $MySQLi->close();
        exit;
    }

    // Start transaction to ensure atomicity
    $MySQLi->begin_transaction();

    try {
        // Delete related user tasks
        $userTaskStmt = $MySQLi->prepare("DELETE FROM `user_tasks` WHERE `task_id` = ?");
        if (!$userTaskStmt) {
            throw new \Exception('Failed to prepare user_tasks deletion statement: ' . $MySQLi->error);
        }
        $userTaskStmt->bind_param("i", $id);
        if (!$userTaskStmt->execute()) {
            throw new \Exception('Failed to delete related user tasks: ' . $userTaskStmt->error);
        }
        $userTaskStmt->close();

        // Delete task
        $stmt = $MySQLi->prepare("DELETE FROM tasks WHERE id = ?");
        if (!$stmt) {
            throw new \Exception('Failed to prepare task deletion statement: ' . $MySQLi->error);
        }
        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new \Exception('Failed to delete task: ' . $stmt->error);
        }
        $stmt->close();

        // Commit transaction if all deletions succeeded
        $MySQLi->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        // Rollback transaction on any error
        $MySQLi->rollback();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

$MySQLi->close();
?>
