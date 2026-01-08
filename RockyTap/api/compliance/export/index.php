<?php

declare(strict_types=1);

/**
 * Export Verification Data API endpoint
 * POST /api/compliance/export
 * Exports verification data for audit purposes
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Authenticate user and require admin access
    $context = UserContext::requireAdmin();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input ?? '{}', true) ?? [];

    // Get export parameters
    $startDate = $data['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $data['end_date'] ?? date('Y-m-d');
    $format = $data['format'] ?? 'json'; // json or csv
    $includeSensitive = $data['include_sensitive'] ?? false;
    $feature = $data['feature'] ?? null;

    $db = Database::ensureConnection();

    // Build query
    $whereClause = "WHERE DATE(v.created_at) BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];

    if ($feature) {
        $whereClause .= ' AND v.feature = :feature';
        $params['feature'] = $feature;
    }

    // Get verifications
    $stmt = $db->prepare(
        "SELECT v.*, u.first_name, u.last_name, u.username, u.language_code
         FROM `wallet_verifications` v
         LEFT JOIN `users` u ON v.user_id = u.id
         {$whereClause}
         ORDER BY v.created_at ASC"
    );
    $stmt->execute($params);
    $verifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Process data based on format
    if ($format === 'csv') {
        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="verification_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        $headers = [
            'Verification ID',
            'User ID',
            'User Name',
            'Feature',
            'Method',
            'Wallet Address',
            'Network',
            'Status',
            'Risk Score',
            'Risk Level',
            'Created At',
            'Verified At',
            'IP Address'
        ];
        
        if ($includeSensitive) {
            $headers[] = 'Rejection Reason';
            $headers[] = 'Admin Override By';
        }
        
        fputcsv($output, $headers);
        
        // CSV rows
        foreach ($verifications as $verification) {
            $row = [
                $verification['id'],
                $verification['user_id'],
                trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? '')),
                $verification['feature'],
                $verification['verification_method'],
                $verification['wallet_address'],
                $verification['wallet_network'],
                $verification['status'],
                $verification['risk_score'],
                $verification['risk_level'],
                $verification['created_at'],
                $verification['verified_at'] ?? '',
                $verification['ip_address'] ?? ''
            ];
            
            if ($includeSensitive) {
                $row[] = $verification['rejection_reason'] ?? '';
                $row[] = $verification['admin_override_by'] ?? '';
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    } else {
        // Generate JSON
        $exportData = [
            'export_metadata' => [
                'exported_at' => date('Y-m-d H:i:s'),
                'exported_by' => $userId,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'format' => 'json',
                'total_records' => count($verifications)
            ],
            'verifications' => array_map(function($verification) use ($includeSensitive) {
                $data = [
                    'verification_id' => $verification['id'],
                    'user_id' => $verification['user_id'],
                    'user_name' => trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? '')),
                    'username' => $verification['username'],
                    'feature' => $verification['feature'],
                    'verification_method' => $verification['verification_method'],
                    'wallet_address' => $verification['wallet_address'],
                    'wallet_network' => $verification['wallet_network'],
                    'status' => $verification['status'],
                    'risk_score' => (int) $verification['risk_score'],
                    'risk_level' => $verification['risk_level'],
                    'created_at' => $verification['created_at'],
                    'verified_at' => $verification['verified_at'],
                    'expires_at' => $verification['expires_at']
                ];
                
                if ($includeSensitive) {
                    $data['rejection_reason'] = $verification['rejection_reason'];
                    $data['admin_override_by'] = $verification['admin_override_by'];
                    $data['admin_override_reason'] = $verification['admin_override_reason'];
                    $data['ip_address'] = $verification['ip_address'];
                    $data['verification_ip'] = $verification['verification_ip'];
                }
                
                return $data;
            }, $verifications)
        ];
        
        Response::jsonSuccess($exportData);
    }

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while exporting data', 500);
}

