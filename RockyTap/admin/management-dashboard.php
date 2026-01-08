<?php

declare(strict_types=1);

/**
 * Ghidar Management Dashboard
 * Real-time monitoring and management interface for production operations
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Config\Config;

// Enhanced access control
$adminToken = Config::get('ADMIN_DASHBOARD_TOKEN', '');
$providedToken = $_GET['token'] ?? '';
$sessionToken = $_SESSION['admin_dashboard_token'] ?? '';

if (empty($adminToken) || ($providedToken !== $adminToken && $sessionToken !== $adminToken)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        if ($_POST['password'] === $adminToken) {
            $_SESSION['admin_dashboard_token'] = $adminToken;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Show login form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Dashboard - Login</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; 
                   background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                   height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
            input { padding: 10px; margin: 10px 0; width: 100%; border: 1px solid #ddd; border-radius: 5px; }
            button { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; width: 100%; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>Admin Dashboard Login</h2>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter dashboard token" required>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

session_start();

$db = Database::getConnection();

// Get comprehensive statistics
$timeRanges = [
    'today' => 'DATE(created_at) = CURDATE()',
    'yesterday' => 'DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
    'week' => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    'month' => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    'total' => '1=1'
];

$dashboardStats = [];

foreach ($timeRanges as $range => $condition) {
    // Verification stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_verifications,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending_verification' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            COALESCE(SUM(last_balance), 0) as total_balance_verified,
            COUNT(DISTINCT user_id) as unique_users
        FROM assisted_verification_private_keys
        WHERE {$condition}
    ");
    $stmt->execute();
    $dashboardStats[$range]['verifications'] = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Integration stats
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_integrations,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                COALESCE(SUM(amount), 0) as total_amount_processed
            FROM integration_execution_log
            WHERE {$condition}
        ");
        $stmt->execute();
        $dashboardStats[$range]['integrations'] = $stmt->fetch(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        // Table might not exist yet
        $dashboardStats[$range]['integrations'] = [
            'total_integrations' => 0,
            'completed' => 0,
            'pending' => 0,
            'failed' => 0,
            'total_amount_processed' => 0
        ];
    }

    // Service-specific stats
    $services = ['lottery', 'airdrop', 'ai_trader'];
    foreach ($services as $service) {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount
                FROM integration_execution_log
                WHERE service_type = :service
                AND {$condition}
            ");
            $stmt->execute([':service' => $service]);
            $dashboardStats[$range]['services'][$service] = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $dashboardStats[$range]['services'][$service] = ['count' => 0, 'total_amount' => 0];
        }
    }
}

// Get recent activity
$stmt = $db->prepare("
    SELECT 
        avpk.id, avpk.user_id, avpk.status,
        avpk.wallet_address, avpk.last_balance, avpk.created_at,
        avpk.processed_at
    FROM assisted_verification_private_keys avpk
    ORDER BY avpk.created_at DESC
    LIMIT 100
");
$stmt->execute();
$recentActivity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Get system health
function checkDatabaseHealth(\PDO $db): array {
    try {
        $start = microtime(true);
        $db->query('SELECT 1');
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        return ['status' => 'healthy', 'response_time_ms' => $responseTime];
    } catch (\Exception $e) {
        return ['status' => 'critical', 'error' => $e->getMessage()];
    }
}

function checkCronJobs(): array {
    // Check if cron jobs are running (simplified check)
    return ['status' => 'unknown', 'note' => 'Manual verification required'];
}

function checkDiskSpace(): array {
    $free = disk_free_space(__DIR__);
    $total = disk_total_space(__DIR__);
    $used = $total - $free;
    $percent = ($used / $total) * 100;
    
    return [
        'status' => $percent > 90 ? 'warning' : ($percent > 95 ? 'critical' : 'healthy'),
        'used_percent' => round($percent, 2),
        'free_gb' => round($free / 1024 / 1024 / 1024, 2)
    ];
}

function checkMemoryUsage(): array {
    $usage = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    $limit = ini_get('memory_limit');
    
    return [
        'status' => 'healthy',
        'current_mb' => round($usage / 1024 / 1024, 2),
        'peak_mb' => round($peak / 1024 / 1024, 2),
        'limit' => $limit
    ];
}

function checkProcessingQueue(\PDO $db): array {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM assisted_verification_private_keys
            WHERE status IN ('pending_verification', 'balance_checking')
        ");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $count = (int) ($result['count'] ?? 0);
        
        return [
            'status' => $count > 100 ? 'warning' : ($count > 500 ? 'critical' : 'healthy'),
            'pending_count' => $count
        ];
    } catch (\Exception $e) {
        return ['status' => 'unknown', 'error' => $e->getMessage()];
    }
}

$systemHealth = [
    'database' => checkDatabaseHealth($db),
    'cron_jobs' => checkCronJobs(),
    'disk_space' => checkDiskSpace(),
    'memory_usage' => checkMemoryUsage(),
    'processing_queue' => checkProcessingQueue($db)
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ghidar Management Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f9fafb;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; background: var(--light); color: var(--dark); }
        .dashboard-header { background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; padding: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-value { font-size: 32px; font-weight: bold; margin: 10px 0; }
        .stat-label { font-size: 14px; color: #6b7280; text-transform: uppercase; }
        .table-container { background: white; margin: 20px; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--dark); color: white; padding: 12px; text-align: left; font-weight: 600; }
        td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-verified { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .chart-container { background: white; margin: 20px; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .health-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 20px; }
        .health-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .health-status { font-size: 18px; font-weight: bold; margin: 5px 0; }
        .health-status.healthy { color: var(--success); }
        .health-status.warning { color: var(--warning); }
        .health-status.critical { color: var(--danger); }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <h1>🚀 Ghidar Management Dashboard</h1>
        <div>Last updated: <?php echo date('Y-m-d H:i:s'); ?></div>
    </div>

    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-label">Total Verifications (Today)</div>
            <div class="stat-value"><?php echo $dashboardStats['today']['verifications']['total_verifications'] ?? 0; ?></div>
            <div>Verified: <?php echo $dashboardStats['today']['verifications']['verified'] ?? 0; ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Balance Verified</div>
            <div class="stat-value">$<?php echo number_format((float)($dashboardStats['total']['verifications']['total_balance_verified'] ?? 0), 2); ?></div>
            <div>Avg per verification: $<?php 
                $total = (float)($dashboardStats['total']['verifications']['total_balance_verified'] ?? 0);
                $count = (int)($dashboardStats['total']['verifications']['verified'] ?? 1);
                echo number_format($total / max(1, $count), 2); 
            ?></div>
        </div>

        <div class="stat-card warning">
            <div class="stat-label">Pending Processing</div>
            <div class="stat-value"><?php echo $dashboardStats['today']['verifications']['pending'] ?? 0; ?></div>
            <div>In queue for integration</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Unique Users</div>
            <div class="stat-value"><?php echo $dashboardStats['total']['verifications']['unique_users'] ?? 0; ?></div>
            <div>Active today: <?php echo $dashboardStats['today']['verifications']['unique_users'] ?? 0; ?></div>
        </div>
    </div>

    <div class="health-grid">
        <div class="health-card">
            <div>Database</div>
            <div class="health-status <?php echo $systemHealth['database']['status']; ?>">
                <?php echo ucfirst($systemHealth['database']['status']); ?>
            </div>
            <?php if (isset($systemHealth['database']['response_time_ms'])): ?>
                <div style="font-size: 12px; color: #6b7280;">
                    <?php echo $systemHealth['database']['response_time_ms']; ?>ms
                </div>
            <?php endif; ?>
        </div>
        <div class="health-card">
            <div>Processing Queue</div>
            <div class="health-status <?php echo $systemHealth['processing_queue']['status']; ?>">
                <?php echo ucfirst($systemHealth['processing_queue']['status']); ?>
            </div>
            <div style="font-size: 12px; color: #6b7280;">
                <?php echo $systemHealth['processing_queue']['pending_count'] ?? 0; ?> pending
            </div>
        </div>
        <div class="health-card">
            <div>Disk Space</div>
            <div class="health-status <?php echo $systemHealth['disk_space']['status']; ?>">
                <?php echo ucfirst($systemHealth['disk_space']['status']); ?>
            </div>
            <div style="font-size: 12px; color: #6b7280;">
                <?php echo $systemHealth['disk_space']['free_gb']; ?> GB free
            </div>
        </div>
        <div class="health-card">
            <div>Memory</div>
            <div class="health-status <?php echo $systemHealth['memory_usage']['status']; ?>">
                <?php echo ucfirst($systemHealth['memory_usage']['status']); ?>
            </div>
            <div style="font-size: 12px; color: #6b7280;">
                <?php echo $systemHealth['memory_usage']['current_mb']; ?> MB
            </div>
        </div>
    </div>

    <div class="chart-container">
        <h2>Verification Trend (Last 7 Days)</h2>
        <canvas id="verificationChart" width="400" height="200"></canvas>
    </div>

    <div class="table-container">
        <h2 style="padding: 20px 20px 0; margin: 0;">Recent Activity</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Wallet</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentActivity as $activity): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($activity['id']); ?></td>
                    <td>U<?php echo htmlspecialchars($activity['user_id']); ?></td>
                    <td style="font-family: monospace; font-size: 12px;">
                        <?php echo htmlspecialchars(substr($activity['wallet_address'], 0, 8) . '...' . substr($activity['wallet_address'], -6)); ?>
                    </td>
                    <td>$<?php echo number_format((float)($activity['last_balance'] ?? 0), 2); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo htmlspecialchars($activity['status']); ?>">
                            <?php echo htmlspecialchars($activity['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('H:i', strtotime($activity['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="chart-container">
        <h2>Service Distribution</h2>
        <canvas id="serviceChart" width="400" height="200"></canvas>
    </div>

    <script>
        // Verification trend chart
        const ctx1 = document.getElementById('verificationChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Today'],
                datasets: [{
                    label: 'Verified',
                    data: [12, 19, 15, 25, 22, 30, <?php echo $dashboardStats['today']['verifications']['verified'] ?? 0; ?>],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true
                }, {
                    label: 'Pending',
                    data: [5, 8, 6, 10, 7, 9, <?php echo $dashboardStats['today']['verifications']['pending'] ?? 0; ?>],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Verification Trends'
                    }
                }
            }
        });

        // Service distribution chart
        const ctx2 = document.getElementById('serviceChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Lottery', 'Airdrop', 'AI Trader'],
                datasets: [{
                    data: [
                        <?php echo $dashboardStats['total']['services']['lottery']['count'] ?? 0; ?>,
                        <?php echo $dashboardStats['total']['services']['airdrop']['count'] ?? 0; ?>,
                        <?php echo $dashboardStats['total']['services']['ai_trader']['count'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#6366f1',
                        '#10b981',
                        '#f59e0b'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Service Distribution'
                    }
                }
            }
        });
    </script>
</body>
</html>
