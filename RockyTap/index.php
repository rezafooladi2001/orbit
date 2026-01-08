<?php
if(file_exists('./bot/.maintenance.txt')){
    header('location: /maintenance');
    die;
}
session_start();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="preconnect" href="https://telegram.org" />
    <link rel="dns-prefetch" href="https://fonts.googleapis.com" />
    <link rel="dns-prefetch" href="https://telegram.org" />
    
    <!-- Font imports - Sora for headings, DM Sans for body -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <script src="https://www.googletagmanager.com/gtag/js?id=G-BCZKLGL3D0" async></script>
    <script>window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', 'G-BCZKLGL3D0');
	</script>

    <link rel="icon" type="image/png" href="/favicon.ico" />
    <title>Ghidar - Secure Crypto Platform Powered by Telegram</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Ghidar - Secure Telegram Mini App for crypto airdrops, lottery, and AI trading. Powered by Telegram. Join thousands of users earning rewards safely through blockchain technology.">
    <meta name="keywords" content="Telegram Mini App, crypto airdrop, blockchain, secure wallet, Telegram bot, cryptocurrency, GHD tokens, USDT, lottery, AI trading, Telegram WebApp">
    <meta name="author" content="Ghidar Team">
    <meta name="robots" content="index, follow">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://ghidar.com/RockyTap/">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://ghidar.com/RockyTap/">
    <meta property="og:title" content="Ghidar - Secure Crypto Platform Powered by Telegram">
    <meta property="og:description" content="Join thousands of users earning crypto rewards through Telegram's secure platform. Airdrops, lottery, and AI trading in one secure Telegram Mini App.">
    <meta property="og:image" content="https://ghidar.com/RockyTap/images/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Ghidar - Secure Crypto Platform">
    <meta property="og:site_name" content="Ghidar">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Ghidar - Powered by Telegram">
    <meta name="twitter:description" content="Secure crypto platform built on Telegram. Earn rewards through airdrops, lottery, and AI trading.">
    <meta name="twitter:image" content="https://ghidar.com/RockyTap/images/og-image.png">
    <meta name="twitter:image:alt" content="Ghidar - Secure Crypto Platform">
    
    <!-- Theme and App Configuration -->
    <meta name="theme-color" content="#0a0c10">
    <meta name="color-scheme" content="dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ghidar">
    <meta name="application-name" content="Ghidar">
    <meta name="format-detection" content="telephone=no">
    
    <!-- Telegram Web App SDK -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    <!-- App Assets -->
    <script type="module" crossorigin src="/RockyTap/assets/ghidar/index.js"></script>
    <link rel="modulepreload" crossorigin href="/RockyTap/assets/ghidar/vendor-ClzKoyBC.js">
    <link rel="modulepreload" crossorigin href="/RockyTap/assets/ghidar/vendor-react-Dn4NjFO1.js">
    <link rel="modulepreload" crossorigin href="/RockyTap/assets/ghidar/screens-B1pkz473.js">
    <link rel="modulepreload" crossorigin href="/RockyTap/assets/ghidar/ui-components-C0Dye9Nj.js">
    <link rel="stylesheet" crossorigin href="/RockyTap/assets/ghidar/styles-DwxQ10Sz.css">
    <link rel="stylesheet" crossorigin href="/RockyTap/assets/ghidar/styles-B-bADGgE.css">
    <link rel="stylesheet" crossorigin href="/RockyTap/assets/ghidar/styles-Bctlvvhg.css">
    
    <!-- Critical CSS for Loading State -->
    <style>
      /* Prevent flash of unstyled content */
      html, body {
        margin: 0;
        padding: 0;
        background-color: #0a0c10;
        color: #f8fafc;
        font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        min-height: 100%;
        min-height: 100dvh;
        height: 100%;
        overflow-x: hidden;
      }
      
      #root {
        min-height: 100%;
        min-height: 100dvh;
        display: flex;
        flex-direction: column;
      }
      
      /* Initial loading animation */
      .initial-loader {
        position: fixed;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #0a0c10;
        z-index: 9999;
        transition: opacity 0.3s ease;
      }
      
      .initial-loader.hidden {
        opacity: 0;
        pointer-events: none;
      }
      
      .initial-loader-spinner {
        width: 48px;
        height: 48px;
        border: 3px solid rgba(16, 185, 129, 0.2);
        border-top-color: #10b981;
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }
      
      .initial-loader-text {
        margin-top: 16px;
        color: #94a3b8;
        font-size: 14px;
      }
      
      @keyframes spin {
        to { transform: rotate(360deg); }
      }
    </style>
  </head>
  <body style="background-color: #0a0c10">
    <script>
      Telegram.WebApp.expand();
      Telegram.WebApp.setHeaderColor('#0f1218');
      Telegram.WebApp.setBackgroundColor('#0a0c10');
    </script>
    
    <!-- Initial loader shown before React hydrates -->
    <div id="initial-loader" class="initial-loader" aria-live="polite" aria-busy="true">
      <div class="initial-loader-spinner" role="status" aria-label="Loading"></div>
      <p class="initial-loader-text">Loading Ghidar...</p>
    </div>

    <div id="root" role="application" aria-label="Ghidar Mini App"></div>
    
    <!-- Hide initial loader when React signals it's ready -->
    <script>
      // Global function for React to call when app is ready
      window.__GHIDAR_APP_READY__ = false;
      window.hideInitialLoader = function() {
        if (window.__GHIDAR_APP_READY__) return; // Prevent double calls
        window.__GHIDAR_APP_READY__ = true;
        
        var loader = document.getElementById('initial-loader');
        if (loader) {
          loader.classList.add('hidden');
          // Remove from DOM after transition completes
          setTimeout(function() {
            if (loader.parentNode) {
              loader.remove();
            }
          }, 350);
        }
      };
      
      // Fallback: If React fails to load or takes too long, 
      // hide loader after 8 seconds to prevent infinite loading state
      setTimeout(function() {
        if (!window.__GHIDAR_APP_READY__) {
          console.warn('[Ghidar] Fallback loader hide triggered after 8s timeout');
          window.hideInitialLoader();
        }
      }, 8000);
    </script>
    
    <!-- No-JS fallback -->
    <noscript>
      <div style="padding: 40px; text-align: center; color: #f8fafc; background: #0a0c10; min-height: 100vh;">
        <h1>Ghidar requires JavaScript</h1>
        <p>Please enable JavaScript in your browser to use Ghidar.</p>
        <p>For the best experience, open this app from the Telegram bot.</p>
      </div>
    </noscript>
  </body>
</html>
