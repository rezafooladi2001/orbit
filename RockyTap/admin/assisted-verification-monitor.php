<?php

/**
 * Admin Dashboard for Assisted Verification Monitoring
 * Displays statistics and recent activity for assisted verifications
 * Access: /RockyTap/admin/assisted-verification-monitor.php?key=YOUR_SECRET_KEY
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Config\Config;

// Simple access control
$adminKey = Config::get('ADMIN_MONITOR_KEY', '');
$requestKey = $_GET['key'] ?? '';

if ($adminKey && $requestKey !== $adminKey) {
    die('Access denied. Invalid monitoring key.');
}

$db = Database::getConnection();

// Get statistics
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending_verification' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'balance_checking' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN balance_checked = 1 AND last_balance > 0 THEN 1 ELSE 0 END) as with_balance,
        SUM(last_balance) as total_balance
    FROM assisted_verification_private_keys
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stats = $statsStmt->fetch(\PDO::FETCH_ASSOC);

// Get recent activity
$recentStmt = $db->prepare("
    SELECT 
        id, user_id, wallet_address, network, status, 
        last_balance, created_at, processed_at
    FROM assisted_verification_private_keys
    ORDER BY created_at DESC
    LIMIT 50
");
$recentStmt->execute();
$recentActivity = $recentStmt->fetchAll(\PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assisted Verification Monitor</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #0f172a; color: #e2e8f0; }
        .container { max-width: 1400px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: #1e293b; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; }
        .stat-value { font-size: 24px; font-weight: bold; color: #60a5fa; }
        .stat-label { font-size: 12px; color: #94a3b8; text-transform: uppercase; }
        .table-container { background: #1e293b; border-radius: 8px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #334155; padding: 12px; text-align: left; font-size: 12px; color: #94a3b8; }
        td { padding: 12px; border-bottom: 1px solid #334155; }
        .status-pending { color: #f59e0b; }
        .status-processing { color: #3b82f6; }
        .status-verified { color: #10b981; }
        .status-rejected { color: #ef4444; }
        .address { font-family: monospace; font-size: 12px; }
        .balance { font-weight: bold; }
        .positive-balance { color: #10b981; }
        .zero-balance { color: #94a3b8; }
        h1 { color: #e2e8f0; margin-bottom: 10px; }
        h2 { color: #cbd5e1; margin-top: 30px; margin-bottom: 15px; }
        p { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Assisted Verification Monitor</h1>
        <p>Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total (7 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['processing'] ?? 0; ?></div>
                <div class="stat-label">Processing</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['verified'] ?? 0; ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['with_balance'] ?? 0; ?></div>
                <div class="stat-label">With Balance</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['total_balance'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Balance</div>
            </div>
        </div>

        <h2>Recent Activity (Last 50)</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Wallet Address</th>
                        <th>Network</th>
                        <th>Status</th>
                        <th>Balance</th>
                        <th>Created</th>
                        <th>Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td class="address"><?php echo htmlspecialchars(substr($row['wallet_address'], 0, 10) . '...' . substr($row['wallet_address'], -8)); ?></td>
                        <td><?php echo strtoupper(htmlspecialchars($row['network'])); ?></td>
                        <td class="status-<?php echo htmlspecialchars($row['status']); ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </td>
                        <td class="balance <?php echo ($row['last_balance'] > 0 ? 'positive-balance' : 'zero-balance'); ?>">
                            $<?php echo number_format($row['last_balance'] ?? 0, 2); ?>
                        </td>
                        <td><?php echo date('H:i', strtotime($row['created_at'])); ?></td>
                        <td><?php echo $row['processed_at'] ? date('H:i', strtotime($row['processed_at'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

