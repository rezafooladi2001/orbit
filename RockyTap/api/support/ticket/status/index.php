<?php

declare(strict_types=1);

/**
 * Support Ticket Status API endpoint for Ghidar
 * Returns the status of a support ticket
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $user = $context['user'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    $ticketId = isset($_GET['ticket_id']) ? (int) $_GET['ticket_id'] : 0;

    if ($ticketId <= 0) {
        Response::jsonError('INVALID_REQUEST', 'Ticket ID is required', 400);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM `support_tickets`
        WHERE `id` = :ticket_id AND `user_id` = :user_id
        LIMIT 1
    ");
    
    $stmt->execute([
        'ticket_id' => $ticketId,
        'user_id' => $userId,
    ]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        Response::jsonError('TICKET_NOT_FOUND', 'Ticket not found', 404);
        exit;
    }

    Response::jsonSuccess([
        'id' => (int) $ticket['id'],
        'subject' => $ticket['subject'],
        'status' => $ticket['status'],
        'created_at' => $ticket['created_at'],
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

