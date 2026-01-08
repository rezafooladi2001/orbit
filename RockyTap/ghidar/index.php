<?php
/**
 * Ghidar Mini App Entry Point
 * Serves the Vite-built React app with proper Telegram WebApp integration
 * 
 * This file reads the Vite-generated index.html and serves it,
 * ensuring correct asset references with cache-busting.
 */

// Check maintenance mode
if (file_exists(__DIR__ . '/../bot/.maintenance.txt')) {
    header('Location: /RockyTap/maintenance');
    exit;
}

// Set proper headers
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CRITICAL: Prevent caching of HTML to ensure users always get latest asset references
// This fixes issues where users have stale cached HTML pointing to old asset filenames
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Path to Vite-generated index.html
$indexHtmlPath = __DIR__ . '/../assets/ghidar/index.html';

// If Vite-generated index.html exists, serve it with enhancements
if (file_exists($indexHtmlPath)) {
    $html = file_get_contents($indexHtmlPath);
    
    // Add cache-busting query parameter to assets (optional, Vite already uses hashes)
    // Load bootstrap for config if available
    $appVersion = '1.0.0';
    $bootstrapPath = __DIR__ . '/../../bootstrap.php';
    if (file_exists($bootstrapPath)) {
        try {
            require_once $bootstrapPath;
            $appVersion = \Ghidar\Config\Config::get('APP_VERSION', '1.0.0');
        } catch (Throwable $e) {
            // Continue without version
        }
    }
    
    // Add version comment for debugging
    $html = str_replace('</head>', "<!-- App Version: {$appVersion} -->\n  </head>", $html);
    
    echo $html;
    exit;
}

// Fallback: If index.html doesn't exist, show error
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0c10">
    <title>Ghidar - Error</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            background: #0a0c10;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .error-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #10b981;
            font-size: 28px;
            margin-bottom: 16px;
        }
        p {
            color: #94a3b8;
            font-size: 16px;
            max-width: 300px;
            line-height: 1.5;
        }
        .retry-btn {
            margin-top: 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 32px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .debug-info {
            margin-top: 40px;
            padding: 16px;
            background: #1e293b;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            color: #64748b;
            max-width: 90%;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Ghidar</h1>
        <p>The application assets could not be loaded. Please try again or contact support.</p>
        <button class="retry-btn" onclick="window.location.reload()">Retry</button>
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            Missing: /assets/ghidar/index.html<br>
            Path checked: <?= htmlspecialchars($indexHtmlPath) ?><br>
            Time: <?= date('Y-m-d H:i:s') ?>
        </div>
    </div>
    <script>
        if (window.Telegram && window.Telegram.WebApp) {
            window.Telegram.WebApp.ready();
            window.Telegram.WebApp.expand();
        }
    </script>
</body>
</html>
