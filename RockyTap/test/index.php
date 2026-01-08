<?php
/**
 * Ghidar API Test Interface
 * Simple diagnostic page accessible via /RockyTap/test/
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0a0c10">
    <title>API Test - Ghidar</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0a0c10 0%, #1a2030 100%);
            color: white;
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 600px; margin: 0 auto; }
        h1 {
            color: #10b981;
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .subtitle { color: #94a3b8; margin-bottom: 30px; font-size: 14px; }
        .section {
            background: rgba(26, 32, 48, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #fbbf24;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        button {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:active {
            transform: translateY(2px);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        button:disabled {
            background: #6b7280;
            cursor: not-allowed;
            box-shadow: none;
        }
        pre {
            background: #0f172a;
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-all;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .success { color: #22c55e; font-weight: 600; }
        .error { color: #ef4444; font-weight: 600; }
        .warning { color: #fbbf24; font-weight: 600; }
        .info { color: #3b82f6; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-error { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge-warning { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .divider { height: 1px; background: rgba(255, 255, 255, 0.1); margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Ghidar API Test</h1>
        <div class="subtitle">Diagnostic tool for testing API connectivity</div>
        
        <div class="section">
            <h2>📡 Telegram SDK Info</h2>
            <pre id="sdk-info">Loading...</pre>
        </div>

        <div class="section">
            <h2>🚀 Run Tests</h2>
            <button onclick="runTests()">Start API Tests</button>
            <pre id="test-results" style="display:none;">Test results will appear here...</pre>
        </div>
    </div>

    <script>
        // Initialize Telegram WebApp
        if (window.Telegram?.WebApp) {
            window.Telegram.WebApp.ready();
            window.Telegram.WebApp.expand();
            window.Telegram.WebApp.setHeaderColor('#0f1218');
            window.Telegram.WebApp.setBackgroundColor('#0a0c10');
        }

        // Display SDK info
        function updateSDKInfo() {
            const sdkInfo = document.getElementById('sdk-info');
            
            const info = {
                telegram_exists: !!window.Telegram,
                webapp_exists: !!window.Telegram?.WebApp,
                initData_length: window.Telegram?.WebApp?.initData?.length || 0,
                platform: window.Telegram?.WebApp?.platform || 'unknown',
                version: window.Telegram?.WebApp?.version || 'unknown',
                page_url: window.location.href,
                user_agent: navigator.userAgent.substring(0, 80) + '...'
            };
            
            if (window.Telegram?.WebApp?.initDataUnsafe?.user) {
                const user = window.Telegram.WebApp.initDataUnsafe.user;
                info.user_id = user.id;
                info.user_name = user.first_name + (user.last_name ? ' ' + user.last_name : '');
            }
            
            sdkInfo.innerHTML = JSON.stringify(info, null, 2);
        }

        async function runTests() {
            const resultsDiv = document.getElementById('test-results');
            const btn = event.target;
            
            btn.disabled = true;
            btn.textContent = 'Running tests...';
            resultsDiv.style.display = 'block';
            
            let output = '';
            
            function log(msg, type = 'info') {
                const classes = {
                    success: 'success',
                    error: 'error',
                    warning: 'warning',
                    info: 'info'
                };
                const className = classes[type] || 'info';
                output += `<span class="${className}">${msg}</span>\n`;
                resultsDiv.innerHTML = output;
                // Scroll to bottom
                resultsDiv.scrollTop = resultsDiv.scrollHeight;
            }
            
            try {
                const initData = window.Telegram?.WebApp?.initData || '';
                
                log('╔═══════════════════════════════════════╗');
                log('║   GHIDAR API DIAGNOSTIC TESTS         ║');
                log('╚═══════════════════════════════════════╝\n');
                
                // Check initData
                log('📋 Checking Telegram Authentication...');
                if (initData && initData.length > 0) {
                    log(`✓ initData present: ${initData.length} characters`, 'success');
                } else {
                    log('✗ initData is EMPTY - authentication will fail!', 'error');
                }
                log('');
                
                // Test 1: Health endpoint (no auth required)
                log('━━━ Test 1: Health Check (No Auth) ━━━');
                try {
                    const healthUrl = '/RockyTap/api/health/';
                    log(`Fetching: ${healthUrl}`);
                    
                    const res1 = await fetch(healthUrl, {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    
                    log(`HTTP ${res1.status} ${res1.statusText}`);
                    const data1 = await res1.json();
                    log(`Response: ${JSON.stringify(data1).substring(0, 150)}...`);
                    
                    if (res1.ok && data1.success) {
                        log('✓ Health endpoint works correctly!', 'success');
                    } else {
                        log('✗ Health endpoint returned error', 'error');
                    }
                } catch (e) {
                    log(`✗ Health check FAILED: ${e.message}`, 'error');
                }
                log('');
                
                // Test 2: Me endpoint (auth required)
                log('━━━ Test 2: User Data (/me/) ━━━');
                try {
                    const meUrl = '/RockyTap/api/me/';
                    log(`Fetching: ${meUrl}`);
                    log(`Sending Telegram-Data header: ${initData ? 'Yes' : 'No'}`);
                    
                    const res2 = await fetch(meUrl, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'Telegram-Data': initData
                        }
                    });
                    
                    log(`HTTP ${res2.status} ${res2.statusText}`);
                    const data2 = await res2.json();
                    log(`Response:\n${JSON.stringify(data2, null, 2)}`);
                    
                    if (res2.ok && data2.success) {
                        log('✓ User authentication works!', 'success');
                        log(`  User ID: ${data2.data.user.id}`);
                        log(`  Username: ${data2.data.user.username || 'N/A'}`);
                        log(`  USDT Balance: ${data2.data.wallet.usdt_balance}`);
                        log(`  GHD Balance: ${data2.data.wallet.ghd_balance}`);
                    } else {
                        log('✗ Authentication failed', 'error');
                        log(`  Error: ${data2.error?.message || 'Unknown error'}`);
                    }
                } catch (e) {
                    log(`✗ /me/ request FAILED: ${e.message}`, 'error');
                }
                log('');
                
                // Test 3: Different URL formats
                log('━━━ Test 3: URL Formats ━━━');
                try {
                    // Relative URL
                    log('Testing relative URL: /RockyTap/api/health/');
                    const res3a = await fetch('/RockyTap/api/health/');
                    log(`  Status: ${res3a.status} ${res3a.ok ? '✓' : '✗'}`, res3a.ok ? 'success' : 'error');
                    
                    // Absolute URL
                    const absUrl = `${window.location.origin}/RockyTap/api/health/`;
                    log(`Testing absolute URL: ${absUrl}`);
                    const res3b = await fetch(absUrl);
                    log(`  Status: ${res3b.status} ${res3b.ok ? '✓' : '✗'}`, res3b.ok ? 'success' : 'error');
                    
                    log('✓ Both URL formats work', 'success');
                } catch (e) {
                    log(`✗ URL format test failed: ${e.message}`, 'error');
                }
                log('');
                
                // Summary
                log('╔═══════════════════════════════════════╗');
                log('║   DIAGNOSTIC COMPLETE                 ║');
                log('╚═══════════════════════════════════════╝');
                
            } catch (error) {
                log(`\n✗ FATAL ERROR: ${error.message}`, 'error');
            }
            
            btn.disabled = false;
            btn.textContent = 'Run Tests Again';
        }

        // Initialize on load
        window.addEventListener('DOMContentLoaded', updateSDKInfo);
        updateSDKInfo();
    </script>
</body>
</html>

