<?php

declare(strict_types=1);

/**
 * Compliance Report Generator Script
 * Generates compliance reports for specified period
 * Usage: php scripts/generate_compliance_reports.php [daily|weekly|monthly]
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Compliance\AutomatedComplianceReporter;

$period = $argv[1] ?? 'daily';

if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
    echo "Invalid period. Use: daily, weekly, or monthly\n";
    exit(1);
}

$reporter = new AutomatedComplianceReporter();
$reports = $reporter->generateComplianceReports($period);

echo "Compliance Reports Generated for: {$period}\n";
echo "Generated at: " . date('Y-m-d H:i:s') . "\n";
echo "\nReports:\n";
foreach ($reports as $reportType => $reportData) {
    echo "  - {$reportType}\n";
}
