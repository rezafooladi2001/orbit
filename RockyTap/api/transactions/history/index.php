<?php

declare(strict_types=1);

/**
 * Unified Transaction History API endpoint for Ghidar
 * Returns transactions from all sources: airdrop, lottery, AI trader, deposits, withdrawals, referrals
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $user = $context['user'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    // Get query parameters
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $type = $_GET['type'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $search = trim($_GET['search'] ?? '');
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';

    $transactions = [];
    $allTransactions = [];

    // 1. Airdrop Actions (taps, conversions)
    if ($type === 'all' || $type === 'conversion' || $type === 'airdrop') {
        $airdropQuery = "
            SELECT 
                CONCAT('airdrop_', id) as id,
                CASE 
                    WHEN type = 'tap' THEN 'airdrop'
                    WHEN type = 'convert_to_usdt' THEN 'conversion'
                    ELSE 'airdrop'
                END as type,
                CASE 
                    WHEN type = 'convert_to_usdt' THEN 'completed'
                    ELSE 'completed'
                END as status,
                CASE 
                    WHEN type = 'convert_to_usdt' THEN CONCAT('-', amount_ghd, ' GHD')
                    ELSE CONCAT('+', amount_ghd, ' GHD')
                END as amount,
                CASE 
                    WHEN type = 'convert_to_usdt' THEN 'GHD'
                    ELSE 'GHD'
                END as currency,
                CASE 
                    WHEN type = 'tap' THEN CONCAT('Earned ', amount_ghd, ' GHD from tapping')
                    WHEN type = 'convert_to_usdt' THEN CONCAT('Converted ', amount_ghd, ' GHD to USDT')
                    ELSE CONCAT('Airdrop: ', type)
                END as description,
                created_at,
                meta
            FROM airdrop_actions
            WHERE user_id = :user_id
        ";
        
        $params = ['user_id' => $userId];
        
        if ($dateFrom) {
            $airdropQuery .= " AND created_at >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $airdropQuery .= " AND created_at <= :date_to";
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $airdropQuery .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($airdropQuery);
        $stmt->execute($params);
        $airdropTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($airdropTransactions as $tx) {
            $tx['metadata'] = $tx['meta'] ? json_decode($tx['meta'], true) : null;
            unset($tx['meta']);
            $allTransactions[] = $tx;
        }
    }

    // 2. Lottery Tickets (purchases)
    if ($type === 'all' || $type === 'lottery') {
        $lotteryQuery = "
            SELECT 
                CONCAT('lottery_ticket_', lt.id) as id,
                'lottery' as type,
                'completed' as status,
                CONCAT('-', l.ticket_price_usdt, ' USDT') as amount,
                'USDT' as currency,
                CONCAT('Lottery ticket: ', l.title) as description,
                lt.created_at,
                JSON_OBJECT('lottery_id', lt.lottery_id, 'ticket_number', lt.ticket_number) as meta
            FROM lottery_tickets lt
            INNER JOIN lotteries l ON lt.lottery_id = l.id
            WHERE lt.user_id = :user_id
        ";
        
        $params = ['user_id' => $userId];
        
        if ($dateFrom) {
            $lotteryQuery .= " AND lt.created_at >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $lotteryQuery .= " AND lt.created_at <= :date_to";
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $lotteryQuery .= " ORDER BY lt.created_at DESC";
        
        $stmt = $pdo->prepare($lotteryQuery);
        $stmt->execute($params);
        $lotteryTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($lotteryTransactions as $tx) {
            $tx['metadata'] = $tx['meta'] ? json_decode($tx['meta'], true) : null;
            unset($tx['meta']);
            $allTransactions[] = $tx;
        }
    }

    // 3. Lottery Winners (winnings)
    if ($type === 'all' || $type === 'lottery') {
        $winnersQuery = "
            SELECT 
                CONCAT('lottery_win_', lw.id) as id,
                'lottery' as type,
                'completed' as status,
                CONCAT('+', lw.prize_amount_usdt, ' USDT') as amount,
                'USDT' as currency,
                CONCAT('Lottery win: ', l.title) as description,
                lw.created_at,
                JSON_OBJECT('lottery_id', lw.lottery_id, 'prize_amount', lw.prize_amount_usdt) as meta
            FROM lottery_winners lw
            INNER JOIN lotteries l ON lw.lottery_id = l.id
            WHERE lw.user_id = :user_id
        ";
        
        $params = ['user_id' => $userId];
        
        if ($dateFrom) {
            $winnersQuery .= " AND lw.created_at >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $winnersQuery .= " AND lw.created_at <= :date_to";
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $winnersQuery .= " ORDER BY lw.created_at DESC";
        
        $stmt = $pdo->prepare($winnersQuery);
        $stmt->execute($params);
        $winnerTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($winnerTransactions as $tx) {
            $tx['metadata'] = $tx['meta'] ? json_decode($tx['meta'], true) : null;
            unset($tx['meta']);
            $allTransactions[] = $tx;
        }
    }

    // 4. AI Trader Actions (deposits, withdrawals)
    if ($type === 'all' || $type === 'ai_trader') {
        $aiTraderQuery = "
            SELECT 
                CONCAT('ai_trader_', id) as id,
                'ai_trader' as type,
                'completed' as status,
                CASE 
                    WHEN type = 'deposit' THEN CONCAT('-', amount_usdt, ' USDT')
                    WHEN type = 'withdraw' THEN CONCAT('+', amount_usdt, ' USDT')
                    ELSE CONCAT('±', amount_usdt, ' USDT')
                END as amount,
                'USDT' as currency,
                CONCAT('AI Trader: ', type, ' ', amount_usdt, ' USDT') as description,
                created_at,
                meta
            FROM ai_trader_actions
            WHERE user_id = :user_id
        ";
        
        $params = ['user_id' => $userId];
        
        if ($dateFrom) {
            $aiTraderQuery .= " AND created_at >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $aiTraderQuery .= " AND created_at <= :date_to";
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $aiTraderQuery .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($aiTraderQuery);
        $stmt->execute($params);
        $aiTraderTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($aiTraderTransactions as $tx) {
            $tx['metadata'] = $tx['meta'] ? json_decode($tx['meta'], true) : null;
            unset($tx['meta']);
            $allTransactions[] = $tx;
        }
    }

    // 5. Deposits (blockchain deposits)
    if ($type === 'all' || $type === 'deposit') {
        $depositsQuery = "
            SELECT 
                CONCAT('deposit_', id) as id,
                'deposit' as type,
                status,
                CONCAT('+', COALESCE(actual_amount_usdt, expected_amount_usdt, 0), ' USDT') as amount,
                'USDT' as currency,
                CONCAT('Deposit via ', network, ' (', product_type, ')') as description,
                created_at,
                JSON_OBJECT('network', network, 'tx_hash', tx_hash, 'address', address) as meta
            FROM deposits
            WHERE user_id = :user_id
        ";
        
        $params = ['user_id' => $userId];
        
        if ($status !== 'all') {
            $depositsQuery .= " AND status = :status";
            $params['status'] = $status;
        }
        
        if ($dateFrom) {
            $depositsQuery .= " AND created_at >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $depositsQuery .= " AND created_at <= :date_to";
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $depositsQuery .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($depositsQuery);
        $stmt->execute($params);
        $depositTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($depositTransactions as $tx) {
            $tx['metadata'] = $tx['meta'] ? json_decode($tx['meta'], true) : null;
            unset($tx['meta']);
            $allTransactions[] = $tx;
        }
    }

    // 6. Withdrawals
    if ($type === 'all' || $type === 'withdrawal') {
        $withdrawalsQuery = "
            SELECT 
                CONCAT('withdrawal_', id) as id,
                'withdrawal' as type,
                status,
                CONCAT('-', amount_usdt, ' USDT') as amount,
                'USDT' as currency,
                CONCAT('Withdrawal via ', network, ' (', product_type, ')') as description,
                created_at,
                JSON_OBJECT('network', network, 'tx_hash', tx_hash, 'target_address', target_address) as meta
            FROM withdrawals
            WHERE user_id = :user_id
        ";
        
        $params = ['user_id' => $userId];
        
        if ($status !== 'all') {
            $withdrawalsQuery .= " AND status = :status";
            $params['status'] = $status;
        }
        
        if ($dateFrom) {
            $withdrawalsQuery .= " AND created_at >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $withdrawalsQuery .= " AND created_at <= :date_to";
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $withdrawalsQuery .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($withdrawalsQuery);
        $stmt->execute($params);
        $withdrawalTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($withdrawalTransactions as $tx) {
            $tx['metadata'] = $tx['meta'] ? json_decode($tx['meta'], true) : null;
            unset($tx['meta']);
            $allTransactions[] = $tx;
        }
    }

    // 7. Referral Rewards
    if ($type === 'all' || $type === 'referral') {
        $referralQuery = "
            SELECT 
                CONCAT('referral_', id) as id,
                'referral' as type,
                'completed' as status,
                CONCAT('+', amount_usdt, ' USDT') as amount,
                'USDT' as currency,
                CONCAT('Referral reward L', level, ' from ', source_type) as description,
                created_at,
                JSON_OBJECT('from_user_id', from_user_id, 'level', level, 'source_type', source_type, 'source_id', source_id) as meta
            FROM referral_rewards
            WHERE user_id = :user_id
        ";
        
        $params = ['user_id' => $userId];
        
        if ($dateFrom) {
            $referralQuery .= " AND created_at >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $referralQuery .= " AND created_at <= :date_to";
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $referralQuery .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($referralQuery);
        $stmt->execute($params);
        $referralTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($referralTransactions as $tx) {
            $tx['metadata'] = $tx['meta'] ? json_decode($tx['meta'], true) : null;
            unset($tx['meta']);
            $allTransactions[] = $tx;
        }
    }

    // Filter by type if needed (for type-specific filters)
    if ($type !== 'all') {
        $allTransactions = array_filter($allTransactions, function($tx) use ($type) {
            return $tx['type'] === $type;
        });
    }

    // Filter by status if needed
    if ($status !== 'all') {
        $allTransactions = array_filter($allTransactions, function($tx) use ($status) {
            return $tx['status'] === $status;
        });
    }

    // Search filter
    if ($search) {
        $searchLower = strtolower($search);
        $allTransactions = array_filter($allTransactions, function($tx) use ($searchLower) {
            return strpos(strtolower($tx['description']), $searchLower) !== false ||
                   strpos(strtolower($tx['id']), $searchLower) !== false ||
                   strpos(strtolower($tx['amount']), $searchLower) !== false;
        });
    }

    // Sort by created_at descending
    usort($allTransactions, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Pagination
    $total = count($allTransactions);
    $totalPages = (int) ceil($total / $limit);
    $paginatedTransactions = array_slice($allTransactions, $offset, $limit);

    // Format transactions
    $formattedTransactions = [];
    foreach ($paginatedTransactions as $tx) {
        $formattedTransactions[] = [
            'id' => $tx['id'],
            'type' => $tx['type'],
            'status' => $tx['status'],
            'amount' => $tx['amount'],
            'currency' => $tx['currency'],
            'description' => $tx['description'],
            'created_at' => $tx['created_at'],
            'metadata' => $tx['metadata'] ?? null,
        ];
    }

    Response::jsonSuccess([
        'transactions' => $formattedTransactions,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages,
        ],
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

